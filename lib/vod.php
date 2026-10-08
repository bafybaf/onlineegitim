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
        $abs = vod_abs_from_rec($rec);
        if ($abs !== '') {
            $js .= '&v=' . (int) @filemtime($abs);
        }
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
    @set_time_limit(180);
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
        $ok = vod_stamp_duration($abs, $hintMs);
        if ($ok) {
            @file_put_contents(vod_marker($abs), '1');
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockPath);
    }
    return $ok || is_file($abs);
}

function vod_webm_duration_known(string $abs): bool
{
    if (vod_ffprobe_ms($abs) > 1000) {
        return true;
    }
    $fp = @fopen($abs, 'rb');
    if ($fp === false) {
        return false;
    }
    $buf = (string) fread($fp, 65536);
    fclose($fp);
    return strpos($buf, "\x44\x89") !== false;
}

function vod_stamp_duration(string $abs, float $hintMs = 0): bool
{
    $remuxed = vod_remux_webm($abs);
    $ms = vod_probe_ms($abs, $hintMs);
    $patched = vod_webm_patch_duration($abs, $ms > 1000 ? $ms : $hintMs, true);
    return $remuxed || $patched;
}

function vod_ffprobe_bin(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    $ff = vod_ffmpeg_bin();
    if ($ff !== '') {
        $p = (string) preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', $ff);
        if ($p !== '' && is_file($p)) {
            $cached = $p;
            return $cached;
        }
    }
    $cands = [
        '/usr/bin/ffprobe',
        '/usr/local/bin/ffprobe',
        'C:\\ffmpeg\\bin\\ffprobe.exe',
        'C:\\xampp\\ffmpeg\\bin\\ffprobe.exe',
        'C:\\xampp\\ffmpeg\\ffprobe.exe',
    ];
    foreach ($cands as $c) {
        if (is_file($c)) {
            $cached = $c;
            return $cached;
        }
    }
    $cmd = PHP_OS_FAMILY === 'Windows' ? 'where ffprobe 2>NUL' : 'command -v ffprobe 2>/dev/null';
    $out = [];
    $code = 1;
    @exec($cmd, $out, $code);
    $found = trim((string) ($out[0] ?? ''));
    if ($code === 0 && $found !== '' && is_file($found)) {
        $cached = $found;
    }
    return $cached;
}

function vod_ffprobe_ms(string $abs): float
{
    $probe = vod_ffprobe_bin();
    if ($probe === '' || !is_file($abs)) {
        return 0.0;
    }
    $cmd = escapeshellarg($probe)
        . ' -v error -analyzeduration 200M -probesize 50M -show_entries format=duration -of default=nk=1:nw=1 '
        . escapeshellarg($abs);
    $out = [];
    $code = 1;
    @exec($cmd . (PHP_OS_FAMILY === 'Windows' ? ' 2>NUL' : ' 2>/dev/null'), $out, $code);
    $raw = strtolower(trim((string) ($out[0] ?? '')));
    if ($raw === '' || $raw === 'n/a' || $raw === 'nan') {
        return 0.0;
    }
    $sec = (float) str_replace(',', '.', $raw);
    if ($code === 0 && $sec > 1 && $sec < 86400) {
        return $sec * 1000.0;
    }
    return 0.0;
}

function vod_probe_ms(string $abs, float $hintMs = 0): float
{
    $ff = vod_ffprobe_ms($abs);
    if ($ff > 1000) {
        return $ff;
    }
    $cluster = vod_webm_last_timecode_ms($abs);
    if ($cluster > 800 && ($hintMs < 2000 || $cluster > $hintMs * 0.25)) {
        return $cluster;
    }
    return $hintMs > 1000 ? $hintMs : $cluster;
}

function vod_length_label(array $rec): string
{
    $sec = (int) ($rec['duration_sec'] ?? 0);
    if ($sec < 1) {
        $sec = max(0, (int) ($rec['mins'] ?? 0)) * 60;
    }
    if ($sec < 1) {
        return '1 dk';
    }
    if ($sec < 60) {
        return $sec . ' sn';
    }
    $m = intdiv($sec, 60);
    $s = $sec % 60;
    return $s > 0 ? ($m . ' dk ' . $s . ' sn') : ($m . ' dk');
}

function ensure_recordings_duration_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec('ALTER TABLE recordings ADD COLUMN duration_sec INT UNSIGNED NULL');
    } catch (Throwable) {
    }
    ensure_recordings_test_schema();
}

function ensure_recordings_test_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec('ALTER TABLE recordings ADD COLUMN is_test TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable) {
    }
    try {
        db()->exec("UPDATE recordings SET is_test = 1 WHERE is_test = 0 AND title LIKE 'Test ·%'");
    } catch (Throwable) {
    }
    try {
        db()->exec(
            "UPDATE recordings rec
             JOIN live_rooms r ON rec.video_path LIKE CONCAT('vod/oda-', r.id, '-%')
             SET rec.is_test = 1
             WHERE rec.is_test = 0 AND r.access_mode = 'allow'"
        );
    } catch (Throwable) {
    }
}

function recording_is_test(array $rec): bool
{
    if ((int) ($rec['is_test'] ?? 0) === 1) {
        return true;
    }
    $title = (string) ($rec['title'] ?? '');
    return str_starts_with($title, 'Test ·');
}

function vod_abs_from_rec(array $rec): string
{
    $rel = trim((string) ($rec['video_path'] ?? ''));
    if ($rel === '') {
        return '';
    }
    if (function_exists('academy_file_readable')) {
        $p = academy_file_readable($rel);
        if ($p) {
            return $p;
        }
    }
    if (function_exists('academy_abs_file')) {
        $p = academy_abs_file($rel);
        if (is_file($p)) {
            return $p;
        }
    }
    return '';
}

function vod_hint_ms(array $rec): float
{
    $sec = (int) ($rec['duration_sec'] ?? 0);
    if ($sec > 1) {
        return $sec * 1000.0;
    }
    return max(0, (int) ($rec['mins'] ?? 0)) * 60 * 1000.0;
}

function vod_sync_recording_length(array $rec): array
{
    $id = (int) ($rec['id'] ?? 0);
    $abs = vod_abs_from_rec($rec);
    if ($id < 1 || $abs === '') {
        return $rec;
    }
    $ms = vod_probe_ms($abs, vod_hint_ms($rec));
    $sec = $ms > 400 ? (int) max(1, (int) round($ms / 1000)) : 0;
    if ($sec < 1) {
        return $rec;
    }
    $mins = max(1, min(480, (int) round($sec / 60)));
    if ((int) ($rec['duration_sec'] ?? 0) === $sec && (int) ($rec['mins'] ?? 0) === $mins) {
        return $rec;
    }
    ensure_recordings_duration_schema();
    try {
        db()->prepare('UPDATE recordings SET duration_sec = ?, mins = ? WHERE id = ?')->execute([$sec, $mins, $id]);
    } catch (Throwable) {
        try {
            db()->prepare('UPDATE recordings SET mins = ? WHERE id = ?')->execute([$mins, $id]);
        } catch (Throwable) {
        }
    }
    $rec['duration_sec'] = $sec;
    $rec['mins'] = $mins;
    return $rec;
}

function vod_prepare_recording(array $rec): array
{
    return vod_sync_recording_length($rec);
}

function vod_file_bytes(string $path): int
{
    if (!is_file($path)) {
        return 0;
    }
    clearstatcache(true, $path);
    $sz = @filesize($path);
    if ($sz === false) {
        return PHP_INT_MAX;
    }
    return (int) $sz;
}

function vod_file_ready(string $path, int $min = 200): bool
{
    return is_file($path) && vod_file_bytes($path) >= $min;
}

function vod_clip_title(string $s, int $n = 160): string
{
    $s = trim($s);
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $n);
    }
    return substr($s, 0, $n);
}

function vod_commit_live_room(PDO $pdo, array $room, int $mins = 0, int $sec = 0): bool
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
        return vod_commit_live_room_locked($pdo, $room, $mins, $id, $sec);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockPath);
    }
}

function vod_commit_live_room_locked(PDO $pdo, array $room, int $mins, int $id, int $sec = 0): bool
{
    @set_time_limit(180);
    $like = 'vod/oda-' . $id . '-%';
    $liveName = 'vod/live-' . $id . '.webm';
    $dup = $pdo->prepare('SELECT id FROM recordings WHERE video_path LIKE ? OR video_path = ? LIMIT 1');
    $dup->execute([$like, $liveName]);
    if ($dup->fetch()) {
        return true;
    }
    $tmp = academy_storage('vod') . '/live-' . $id . '.webm';
    $name = '';
    $dest = '';
    if (vod_file_ready($tmp)) {
        $name = 'vod/oda-' . $id . '-' . date('Ymd-His') . '.webm';
        $dest = academy_storage() . '/' . $name;
        @set_time_limit(180);
        if (!@rename($tmp, $dest)) {
            $dest = $tmp;
            $name = 'vod/' . basename($tmp);
        } elseif (is_file($tmp) && @realpath($tmp) !== @realpath($dest)) {
            @unlink($tmp);
        }
    } else {
        $found = glob(academy_storage() . '/vod/oda-' . $id . '-*.webm') ?: [];
        rsort($found);
        if (!$found || !vod_file_ready($found[0])) {
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
    $bytes = vod_file_bytes($dest);
    $hintMs = $sec > 1 ? $sec * 1000.0 : max(0, $mins) * 60 * 1000.0;
    $stamped = false;
    if ($bytes > 0) {
        $stamped = vod_stamp_duration($dest, $hintMs);
        if ($stamped) {
            @file_put_contents(vod_marker($dest), '1');
        }
    }
    $fileMs = $bytes > 0 ? vod_probe_ms($dest, $hintMs) : 0.0;
    $jsSec = $sec > 1 ? $sec : ($mins > 0 ? $mins * 60 : 0);
    $sec = $fileMs > 400 ? (int) max(1, (int) round($fileMs / 1000)) : max(1, $jsSec);
    $sec = min(28800, $sec);
    $mins = max(1, (int) round($sec / 60));
    $mins = min(480, $mins);
    $isTest = function_exists('live_room_is_test') && live_room_is_test($room);
    if ($isTest && !str_starts_with($label, 'Test ·')) {
        $label = 'Test · ' . $label;
    }
    ensure_recordings_duration_schema();
    $title = vod_clip_title($label);
    $okRow = false;
    try {
        $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, duration_sec, recorded_on, video_url, video_path, is_test) VALUES (?,?,?,?,?,CURDATE(),NULL,?,?)')
            ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $sec, $name, $isTest ? 1 : 0]);
        $okRow = true;
    } catch (Throwable $e) {
        try {
            $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, duration_sec, recorded_on, video_url, video_path) VALUES (?,?,?,?,?,CURDATE(),NULL,?)')
                ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $sec, $name]);
            $okRow = true;
        } catch (Throwable $e2) {
            try {
                $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path) VALUES (?,?,?,?,CURDATE(),NULL,?)')
                    ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $name]);
                $okRow = true;
            } catch (Throwable $e3) {
                return is_file($dest);
            }
        }
    }
    if ($okRow && !$isTest && function_exists('notify_group_students')) {
        notify_group_students((int) $room['group_id'], 'Ders kaydı hazır', $label, url('ogrenci/kayitlar'));
    }
    return true;
}

function vod_recover_pending_rooms(PDO $pdo, ?int $teacherId = null): int
{
    $n = 0;
    $files = array_merge(
        glob(academy_storage('vod') . '/live-*.webm') ?: [],
        glob(academy_storage('vod') . '/oda-*.webm') ?: []
    );
    foreach ($files as $file) {
        $base = str_replace('\\', '/', (string) $file);
        if (!preg_match('/(?:live-|oda-)(\d+)/', basename($base), $m)) {
            continue;
        }
        if (!vod_file_ready($file)) {
            continue;
        }
        $st = $pdo->prepare('SELECT * FROM live_rooms WHERE id = ?');
        $st->execute([(int) $m[1]]);
        $room = $st->fetch();
        if (!$room) {
            continue;
        }
        if ($teacherId !== null) {
            $tid = (int) $room['teacher_id'];
            $gid = (int) $room['group_id'];
            if ($tid !== $teacherId && !(function_exists('group_has_teacher') && group_has_teacher($gid, $teacherId))) {
                continue;
            }
        }
        if (vod_commit_live_room($pdo, $room, 0)) {
            $n++;
        }
    }
    return $n;
}

function vod_recover_teacher_pending(PDO $pdo, int $teacherId): int
{
    return vod_recover_pending_rooms($pdo, $teacherId);
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
        . ' -y -hide_banner -loglevel error -analyzeduration 200M -probesize 50M -fflags +genpts -i '
        . escapeshellarg($abs)
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
    $tail = min(12 * 1024 * 1024, (int) $size);
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
    $ms = max($clusterMs, $hintMs);
    if ($clusterMs > 800 && $ms < $clusterMs + 250) {
        $ms = $clusterMs + 250.0;
    }
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
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
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
        if (connection_aborted()) {
            break;
        }
        $chunk = fread($fp, min(65536, $left));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
    }
    fclose($fp);
    exit;
}
