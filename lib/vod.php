<?php
declare(strict_types=1);

function vod_secret(): string
{
    $pass = defined('DB_PASS') ? (string) DB_PASS : '';
    $name = defined('DB_NAME') ? (string) DB_NAME : 'oi';
    return hash('sha256', $pass . '|' . $name . '|vod-play');
}

function vod_play_token(int $recId, int $userId): string
{
    $slot = intdiv(time(), 3600);
    $sig = hash_hmac('sha256', $recId . '|' . $userId . '|' . $slot, vod_secret());
    return $slot . '.' . substr($sig, 0, 32);
}

function vod_play_token_ok(string $token, int $recId, int $userId): bool
{
    if (!preg_match('/^(\d{6,12})\.([a-f0-9]{32})$/', $token, $m)) {
        return false;
    }
    $slot = (int) $m[1];
    $now = intdiv(time(), 3600);
    if ($slot < $now - 3 || $slot > $now + 1) {
        return false;
    }
    $sig = substr(hash_hmac('sha256', $recId . '|' . $userId . '|' . $slot, vod_secret()), 0, 32);
    return hash_equals($sig, $m[2]);
}

function vod_play_url(int $recId, int $userId): string
{
    return url('api/dosya.php?tur=video&id=' . $recId . '&t=' . rawurlencode(vod_play_token($recId, $userId)));
}

function vod_player_src(array $rec, int $userId): array
{
    $js = '';
    $ext = '';
    if (!empty($rec['video_path'])) {
        $js = vod_play_url((int) $rec['id'], $userId);
    } elseif (!empty($rec['video_url'])) {
        $ext = (string) $rec['video_url'];
    }
    return [$js, $ext];
}

function vod_is_direct_navigation(): bool
{
    $dest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
    if (in_array($dest, ['document', 'iframe', 'embed', 'object', 'frame'], true)) {
        return true;
    }
    $mode = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if ($dest === '' && ($mode === 'navigate' || str_starts_with($accept, 'text/html'))) {
        return true;
    }
    return false;
}

function vod_ffmpeg_bin(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    $cands = [
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\xampp\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\xampp\\ffmpeg\\ffmpeg.exe',
    ];
    foreach ($cands as $c) {
        if (is_file($c)) {
            $cached = $c;
            return $cached;
        }
    }
    $cmd = PHP_OS_FAMILY === 'Windows' ? 'where ffmpeg 2>NUL' : 'command -v ffmpeg 2>/dev/null';
    $out = [];
    $code = 1;
    @exec($cmd, $out, $code);
    $found = trim((string) ($out[0] ?? ''));
    if ($code === 0 && $found !== '' && is_file($found)) {
        $cached = $found;
    }
    return $cached;
}

function vod_marker(string $abs): string
{
    return $abs . '.ok';
}

function vod_ensure_playable(string $abs, float $hintMs = 0): bool
{
    if (!is_file($abs) || !is_readable($abs)) {
        return false;
    }
    $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
    if ($ext !== 'webm') {
        return true;
    }
    if (is_file(vod_marker($abs))) {
        return true;
    }
    @set_time_limit(12);
    $lockPath = $abs . '.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        return false;
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return is_file($abs);
    }
    $ok = false;
    try {
        if (is_file(vod_marker($abs))) {
            $ok = true;
        } else {
            $ok = vod_webm_patch_duration($abs, $hintMs, false);
            if ($ok) {
                @file_put_contents(vod_marker($abs), '1');
            }
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockPath);
    }
    return $ok || is_file($abs);
}

function vod_clip_title(string $s, int $n = 160): string
{
    $s = trim($s);
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $n);
    }
    return substr($s, 0, $n);
}

function vod_commit_live_room(PDO $pdo, array $room, int $mins = 0): bool
{
    $id = (int) ($room['id'] ?? 0);
    if ($id < 1) {
        return false;
    }
    $lockPath = academy_storage('vod') . '/live-' . $id . '.commit.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        return false;
    }
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        return false;
    }
    try {
        return vod_commit_live_room_locked($pdo, $room, $mins, $id);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockPath);
    }
}

function vod_commit_live_room_locked(PDO $pdo, array $room, int $mins, int $id): bool
{
    $like = 'vod/oda-' . $id . '-%';
    $dup = $pdo->prepare('SELECT id FROM recordings WHERE video_path LIKE ? LIMIT 1');
    $dup->execute([$like]);
    if ($dup->fetch()) {
        return true;
    }
    $tmp = academy_storage('vod') . '/live-' . $id . '.webm';
    $name = '';
    $dest = '';
    if (is_file($tmp) && filesize($tmp) >= 200) {
        $name = 'vod/oda-' . $id . '-' . date('Ymd-His') . '.webm';
        $dest = academy_storage() . '/' . $name;
        if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) {
            return false;
        }
        if (is_file($tmp) && realpath($tmp) !== realpath($dest)) {
            @unlink($tmp);
        }
    } else {
        $found = glob(academy_storage() . '/vod/oda-' . $id . '-*.webm') ?: [];
        rsort($found);
        if (!$found || !is_file($found[0]) || filesize($found[0]) < 200) {
            return false;
        }
        $dest = $found[0];
        $name = 'vod/' . basename($dest);
    }
    $topic = trim((string) ($room['topic'] ?? ''));
    $title = trim((string) ($room['title'] ?? 'Ders'));
    $when = date('d.m.Y H:i');
    $teacher = trim((string) ($room['teacher_name'] ?? ''));
    if ($teacher === '' && !empty($room['teacher_id'])) {
        $tn = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $tn->execute([(int) $room['teacher_id']]);
        $teacher = trim((string) ($tn->fetchColumn() ?: ''));
    }
    $label = $topic !== '' && $topic !== 'Ders' ? $topic . ' — ' . $title : $title;
    $label .= ' — ' . $when;
    if ($teacher !== '') {
        $label .= ' — ' . $teacher;
    }
    $fileMs = is_file($dest) ? vod_webm_last_timecode_ms($dest) : 0.0;
    if ($fileMs > 400) {
        $mins = max(1, (int) ceil($fileMs / 60000));
    } else {
        $mins = max(1, $mins);
    }
    $mins = min(300, $mins);
    $patchMs = $fileMs > 400 ? $fileMs : ($mins * 60 * 1000.0);
    if (function_exists('vod_webm_patch_duration') && is_file($dest)) {
        vod_webm_patch_duration($dest, $patchMs);
    }
    try {
        $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path) VALUES (?,?,?,?,CURDATE(),NULL,?)')
            ->execute([(int) $room['group_id'], (int) $room['teacher_id'], vod_clip_title($label), $mins, $name]);
    } catch (Throwable $e) {
        return is_file($dest);
    }
    if (function_exists('notify_group_students')) {
        notify_group_students((int) $room['group_id'], 'Ders kaydı hazır', $label, url('ogrenci/kayitlar'));
    }
    return true;
}

function vod_recover_teacher_pending(PDO $pdo, int $teacherId): int
{
    $n = 0;
    foreach (glob(academy_storage('vod') . '/live-*.webm') ?: [] as $file) {
        if (!preg_match('/live-(\d+)\.webm$/', str_replace('\\', '/', $file), $m)) {
            continue;
        }
        if (!is_file($file) || filesize($file) < 200) {
            continue;
        }
        $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ? AND teacher_id = ?');
        $st->execute([(int) $m[1], $teacherId]);
        $room = $st->fetch();
        if ($room && vod_commit_live_room($pdo, $room, 0)) {
            $n++;
        }
    }
    foreach (glob(academy_storage('vod') . '/oda-*.webm') ?: [] as $file) {
        if (!preg_match('/oda-(\d+)-/', basename($file), $m)) {
            continue;
        }
        $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ? AND teacher_id = ?');
        $st->execute([(int) $m[1], $teacherId]);
        $room = $st->fetch();
        if ($room && vod_commit_live_room($pdo, $room, 0)) {
            $n++;
        }
    }
    return $n;
}

function vod_remux_webm(string $abs): bool
{
    $ff = vod_ffmpeg_bin();
    if ($ff === '') {
        return false;
    }
    $tmp = $abs . '.tmp.webm';
    @unlink($tmp);
    $cmd = escapeshellarg($ff)
        . ' -y -hide_banner -loglevel error -fflags +genpts -i ' . escapeshellarg($abs)
        . ' -c copy -avoid_negative_ts make_zero ' . escapeshellarg($tmp);
    $out = [];
    $code = 1;
    @exec($cmd . (PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null'), $out, $code);
    $orig = (int) filesize($abs);
    $fresh = is_file($tmp) ? (int) filesize($tmp) : 0;
    if ($code === 0 && $fresh > 800 && $fresh >= (int) ($orig * 0.5)) {
        if (!@rename($tmp, $abs)) {
            @copy($tmp, $abs);
            @unlink($tmp);
        }
        return is_file($abs) && filesize($abs) > 800;
    }
    @unlink($tmp);
    return false;
}

function vod_webm_vint_width(int $first): int
{
    $width = 1;
    $mask = 0x80;
    while ($width <= 8 && ($first & $mask) === 0) {
        $width++;
        $mask >>= 1;
    }
    return $width <= 8 ? $width : 0;
}

function vod_webm_read_vint(string $buf, int &$o, int $len): ?int
{
    if ($o >= $len) {
        return null;
    }
    $first = ord($buf[$o]);
    $width = vod_webm_vint_width($first);
    if ($width < 1 || $o + $width > $len) {
        return null;
    }
    $mask = 0x80 >> ($width - 1);
    $unknown = true;
    $val = $first & ($mask - 1);
    if (($first & $mask) === 0) {
        return null;
    }
    for ($i = 1; $i < $width; $i++) {
        $b = ord($buf[$o + $i]);
        $val = ($val << 8) | $b;
        if ($b !== 0xFF) {
            $unknown = false;
        }
    }
    if ($width === 1 && $first === 0xFF) {
        $unknown = true;
    } elseif ($val !== (1 << (7 * $width)) - 1) {
        $unknown = false;
    }
    $o += $width;
    return $unknown ? -1 : $val;
}

function vod_webm_read_id(string $buf, int &$o, int $len): ?string
{
    if ($o >= $len) {
        return null;
    }
    $width = vod_webm_vint_width(ord($buf[$o]));
    if ($width < 1 || $width > 4 || $o + $width > $len) {
        return null;
    }
    $id = substr($buf, $o, $width);
    $o += $width;
    return $id;
}

function vod_pack_be_float(float $value, int $bytes): string
{
    $fmt = $bytes === 4 ? 'f' : 'd';
    $raw = pack($fmt, $value);
    if (pack('S', 1) === pack('v', 1)) {
        $raw = strrev($raw);
    }
    return $raw;
}

function vod_webm_last_timecode_ms(string $abs): float
{
    $size = filesize($abs);
    if ($size === false || $size < 32) {
        return 0;
    }
    $tail = min(4 * 1024 * 1024, (int) $size);
    $fp = @fopen($abs, 'rb');
    if ($fp === false) {
        return 0;
    }
    fseek($fp, (int) $size - $tail);
    $buf = (string) fread($fp, $tail);
    fclose($fp);
    $last = 0.0;
    $len = strlen($buf);
    $needle = "\x1F\x43\xB6\x75";
    $pos = 0;
    while (($i = strpos($buf, $needle, $pos)) !== false) {
        $o = $i + 4;
        $clSize = vod_webm_read_vint($buf, $o, $len);
        if ($clSize === null) {
            $pos = $i + 1;
            continue;
        }
        $end = $clSize < 0 ? min($len, $o + 64) : min($len, $o + min($clSize, 64));
        $id = vod_webm_read_id($buf, $o, $end);
        if ($id === "\xE7") {
            $n = vod_webm_read_vint($buf, $o, $end);
            if ($n !== null && $n > 0 && $o + $n <= $end) {
                $tc = 0;
                for ($k = 0; $k < $n; $k++) {
                    $tc = ($tc << 8) | ord($buf[$o + $k]);
                }
                if ($tc > $last) {
                    $last = (float) $tc;
                }
            }
        }
        $pos = $i + 1;
    }
    return $last;
}

function vod_webm_patch_duration(string $abs, float $hintMs, bool $allowRewrite = true): bool
{
    $size = filesize($abs);
    if ($size === false || $size < 64) {
        return false;
    }
    $headLen = min(262144, (int) $size);
    $fp = @fopen($abs, 'rb');
    if ($fp === false) {
        return false;
    }
    $buf = (string) fread($fp, $headLen);
    fclose($fp);
    $len = strlen($buf);
    $o = 0;
    $id = vod_webm_read_id($buf, $o, $len);
    if ($id !== "\x1A\x45\xDF\xA3") {
        return false;
    }
    $ebmlSize = vod_webm_read_vint($buf, $o, $len);
    if ($ebmlSize === null || $ebmlSize < 0) {
        return false;
    }
    $o += $ebmlSize;
    $segId = vod_webm_read_id($buf, $o, $len);
    if ($segId !== "\x18\x53\x80\x67") {
        return false;
    }
    vod_webm_read_vint($buf, $o, $len);
    $durOff = -1;
    $durBytes = 0;
    $infoSizeOff = -1;
    $infoSizeWidth = 0;
    $infoSize = -1;
    $infoPayload = -1;
    $guard = 0;
    while ($o + 3 < $len && $guard++ < 400) {
        $elId = vod_webm_read_id($buf, $o, $len);
        $sizeOff = $o;
        $elSize = vod_webm_read_vint($buf, $o, $len);
        if ($elId === null || $elSize === null) {
            break;
        }
        if ($elId === "\x15\x49\xA9\x66") {
            $infoSizeOff = $sizeOff;
            $infoSizeWidth = $o - $sizeOff;
            $infoSize = $elSize;
            $infoPayload = $o;
            $infoEnd = $elSize < 0 ? $len : min($len, $o + $elSize);
            $io = $o;
            $ig = 0;
            while ($io + 3 < $infoEnd && $ig++ < 80) {
                $cid = vod_webm_read_id($buf, $io, $infoEnd);
                $csz = vod_webm_read_vint($buf, $io, $infoEnd);
                if ($cid === null || $csz === null || $csz < 0) {
                    break;
                }
                if ($cid === "\x44\x89" && ($csz === 4 || $csz === 8)) {
                    $durOff = $io;
                    $durBytes = $csz;
                    break;
                }
                $io += $csz;
            }
            break;
        }
        if ($elSize < 0) {
            break;
        }
        $o += $elSize;
    }
    $clusterMs = vod_webm_last_timecode_ms($abs);
    $ms = $clusterMs > 800 ? $clusterMs + 4000.0 : $hintMs;
    if ($ms < 2000) {
        return false;
    }
    if ($durOff >= 0 && $durBytes >= 4) {
        $packed = vod_pack_be_float($ms, $durBytes);
        if (strlen($packed) !== $durBytes) {
            return false;
        }
        $out = @fopen($abs, 'r+b');
        if ($out === false) {
            return false;
        }
        fseek($out, $durOff);
        $wrote = fwrite($out, $packed);
        fclose($out);
        return $wrote === $durBytes;
    }
    if (!$allowRewrite) {
        return false;
    }
    if ($infoSizeOff < 0 || $infoPayload < 0 || $infoSize < 1 || $infoSizeWidth < 1) {
        return false;
    }
    $newSize = $infoSize + 11;
    $maxSameWidth = (1 << (7 * $infoSizeWidth)) - 2;
    if ($newSize > $maxSameWidth) {
        return false;
    }
    $insert = "\x44\x89\x88" . vod_pack_be_float($ms, 8);
    if (strlen($insert) !== 11) {
        return false;
    }
    $sizeByte = vod_webm_encode_vint($newSize, $infoSizeWidth);
    if ($sizeByte === '') {
        return false;
    }
    $tmp = $abs . '.dur.webm';
    $src = @fopen($abs, 'rb');
    $dst = @fopen($tmp, 'wb');
    if ($src === false || $dst === false) {
        if ($src) {
            fclose($src);
        }
        if ($dst) {
            fclose($dst);
        }
        return false;
    }
    fwrite($dst, substr($buf, 0, $infoSizeOff));
    fwrite($dst, $sizeByte);
    $payloadOff = $infoPayload;
    $copyFrom = $payloadOff + $infoSize;
    fwrite($dst, substr($buf, $payloadOff, $infoSize));
    fwrite($dst, $insert);
    if ($copyFrom < $len) {
        fwrite($dst, substr($buf, $copyFrom));
    }
    $fileSize = (int) $size;
    if ($fileSize > $headLen) {
        fseek($src, $headLen);
        while (!feof($src)) {
            $chunk = fread($src, 1024 * 256);
            if ($chunk === false || $chunk === '') {
                break;
            }
            fwrite($dst, $chunk);
        }
    }
    fclose($src);
    fclose($dst);
    if (!is_file($tmp) || filesize($tmp) < $fileSize) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $abs)) {
        @copy($tmp, $abs);
        @unlink($tmp);
    }
    return is_file($abs) && filesize($abs) >= $fileSize;
}

function vod_webm_encode_vint(int $value, int $width): string
{
    if ($width < 1 || $width > 8 || $value < 0) {
        return '';
    }
    $max = (1 << (7 * $width)) - 2;
    if ($value > $max) {
        return '';
    }
    $bytes = [];
    $v = $value;
    for ($i = 0; $i < $width; $i++) {
        $bytes[] = $v & 0xFF;
        $v >>= 8;
    }
    $bytes = array_reverse($bytes);
    $bytes[0] |= 1 << (8 - $width);
    $out = '';
    foreach ($bytes as $b) {
        $out .= chr($b);
    }
    return $out;
}

function vod_send_file(string $abs, string $mime): void
{
    $size = filesize($abs);
    if ($size === false || $size < 1) {
        http_response_code(404);
        exit('Dosya yok.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $start = 0;
    $end = $size - 1;
    $code = 200;
    $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
    if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $len = (int) $m[2];
            $start = max(0, $size - $len);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = (int) $m[2];
            }
        }
        if ($start > $end || $start >= $size || $end >= $size) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $code = 206;
    }
    header('Accept-Ranges: bytes');
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline');
    header('Content-Length: ' . (string) ($end - $start + 1));
    if ($code === 206) {
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
    $fp = fopen($abs, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit;
    }
    fseek($fp, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
    }
    fclose($fp);
    exit;
}
