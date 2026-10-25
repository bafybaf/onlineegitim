<?php

function live_page_host(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1');
    $host = preg_replace('/:\d+$/', '', $host) ?: '127.0.0.1';
    return $host;
}

function live_obs_host(): string
{
    $defined = defined('LIVE_HOST') ? trim((string) LIVE_HOST) : '';
    if ($defined !== '') {
        return preg_replace('/:\d+$/', '', $defined) ?: $defined;
    }
    $override = trim((string) setting('live_host'));
    if ($override !== '') {
        return preg_replace('/:\d+$/', '', $override) ?: $override;
    }
    return live_page_host();
}

function live_public_host(): string
{
    return live_page_host();
}

function live_page_origin(): string
{
    $https = function_exists('security_https') && security_https();
    return ($https ? 'https' : 'http') . '://' . live_page_host();
}

function live_in_docker(): bool
{
    return defined('DB_HOST') && DB_HOST === 'db';
}

function live_strip_legacy_cdn(string $base): string
{
    $base = rtrim($base, '/');
    if ($base === '') {
        return '';
    }
    $legacy = [
        'https://hls.onlineilahiyat.com',
        'http://hls.onlineilahiyat.com',
        'https://whep.onlineilahiyat.com',
        'http://whep.onlineilahiyat.com',
    ];
    if (live_in_docker() && in_array(strtolower($base), $legacy, true)) {
        return '';
    }
    return $base;
}

function live_hls_base(): string
{
    $base = live_strip_legacy_cdn(defined('LIVE_HLS_BASE') ? (string) LIVE_HLS_BASE : '');
    if ($base !== '') {
        return $base;
    }
    if (live_in_docker()) {
        return rtrim(live_page_origin(), '/') . '/mtx-hls';
    }
    return 'http://' . live_page_host() . ':8888';
}

function live_whep_base(): string
{
    $base = live_strip_legacy_cdn(defined('LIVE_WHEP_BASE') ? (string) LIVE_WHEP_BASE : '');
    if ($base !== '') {
        return $base;
    }
    if (live_in_docker()) {
        return rtrim(live_page_origin(), '/') . '/mtx-whep';
    }
    return 'http://' . live_page_host() . ':8889';
}

function live_stream_paths(string $streamKey): array
{
    $key = trim($streamKey);
    if ($key === '') {
        return [];
    }
    $enc = rawurlencode($key);
    return [$enc, 'live/' . $enc];
}

function live_rtmp_url(): string
{
    return 'rtmp://' . live_obs_host() . ':1935/live';
}

function live_cf_kind(string $streamKey): string
{
    return str_ends_with($streamKey, '-screen') ? 'screen' : 'cam';
}

function live_cf_url(string $kind, string $dir): string
{
    $kind = $kind === 'screen' ? 'screen' : 'cam';
    $dir = $dir === 'whip' ? 'whip' : 'whep';
    $const = $kind === 'screen'
        ? ($dir === 'whip' ? 'CF_STREAM_WHIP_SCREEN' : 'CF_STREAM_WHEP_SCREEN')
        : ($dir === 'whip' ? 'CF_STREAM_WHIP' : 'CF_STREAM_WHEP');
    $set = $kind === 'screen'
        ? ($dir === 'whip' ? 'cf_stream_whip_screen' : 'cf_stream_whep_screen')
        : ($dir === 'whip' ? 'cf_stream_whip' : 'cf_stream_whep');
    $v = defined($const) ? trim((string) constant($const)) : '';
    if ($v === '' && function_exists('setting')) {
        $v = trim(setting($set));
    }
    return rtrim($v, '/');
}

function live_cf_ready(): bool
{
    return live_cf_url('cam', 'whip') !== '' && live_cf_url('cam', 'whep') !== '';
}

function live_cf_from_url(string $url): array
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
    $uid = explode('/', $path)[0] ?? '';
    if ($host === '' || $uid === '' || !preg_match('/^customer-[a-z0-9]+\.cloudflarestream\.com$/', $host)) {
        return ['host' => '', 'uid' => ''];
    }
    if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $uid)) {
        return ['host' => '', 'uid' => ''];
    }
    return ['host' => $host, 'uid' => $uid];
}

function live_cf_hls_url(string $kind): string
{
    $parts = live_cf_from_url(live_cf_url($kind, 'whep'));
    if ($parts['host'] === '' || $parts['uid'] === '') {
        $parts = live_cf_from_url(live_cf_url($kind, 'whip'));
    }
    if ($parts['host'] === '' || $parts['uid'] === '') {
        return '';
    }
    return 'https://' . $parts['host'] . '/' . $parts['uid'] . '/manifest/video.m3u8';
}

function live_cf_api_account(): string
{
    $v = defined('CF_STREAM_ACCOUNT') ? trim((string) CF_STREAM_ACCOUNT) : '';
    if ($v === '' && function_exists('setting')) {
        $v = trim(setting('cf_stream_account'));
    }
    return $v;
}

function live_cf_api_token(): string
{
    $v = defined('CF_STREAM_TOKEN') ? trim((string) CF_STREAM_TOKEN) : '';
    if ($v === '' && function_exists('setting')) {
        $v = trim(setting('cf_stream_token'));
    }
    return $v;
}

function live_cf_api_ready(): bool
{
    return live_cf_api_account() !== '' && live_cf_api_token() !== '';
}

function live_cf_api(string $path, string $method = 'GET', ?string $json = null, int $timeout = 25): ?array
{
    $acc = live_cf_api_account();
    $tok = live_cf_api_token();
    if ($acc === '' || $tok === '' || !function_exists('curl_init')) {
        return null;
    }
    $path = ltrim($path, '/');
    if (!preg_match('#^stream(/[A-Za-z0-9_./?-]+)?$#', $path)) {
        return null;
    }
    $method = strtoupper($method);
    if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
        return null;
    }
    $url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($acc) . '/' . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $tok,
        'Content-Type: application/json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(10, $timeout),
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($json !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        return null;
    }
    $j = json_decode((string) $raw, true);
    if (!is_array($j) || empty($j['success'])) {
        return null;
    }
    return $j;
}

function live_cf_uid_from_ref(string $ref): string
{
    $ref = trim($ref);
    if (preg_match('#(?:^cf/|cloudflarestream\.com/)([A-Za-z0-9_-]{16,64})#i', $ref, $m)) {
        return $m[1];
    }
    return '';
}

function live_cf_delete_video(string $uid): bool
{
    if ($uid === '' || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $uid)) {
        return false;
    }
    return live_cf_api('stream/' . rawurlencode($uid), 'DELETE') !== null;
}

function live_cf_upload_video(string $absPath, string $title = ''): ?array
{
    $acc = live_cf_api_account();
    $tok = live_cf_api_token();
    if ($acc === '' || $tok === '' || !function_exists('curl_init') || !class_exists('CURLFile')) {
        return null;
    }
    $absPath = (string) realpath($absPath);
    if ($absPath === '' || !is_file($absPath) || !is_readable($absPath)) {
        return null;
    }
    $bytes = (int) @filesize($absPath);
    if ($bytes < 8000 || $bytes > 4 * 1024 * 1024 * 1024) {
        return null;
    }
    $name = trim($title);
    if ($name === '') {
        $name = basename($absPath);
    }
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 120);
    } else {
        $name = substr($name, 0, 120);
    }
    $url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($acc) . '/stream';
    $ch = curl_init($url);
    $file = new CURLFile($absPath, 'video/webm', basename($absPath));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $tok,
            'Upload-Name: ' . rawurlencode($name),
        ],
        CURLOPT_POSTFIELDS => ['file' => $file],
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        return null;
    }
    $j = json_decode((string) $raw, true);
    $row = is_array($j) && !empty($j['success']) && is_array($j['result'] ?? null) ? $j['result'] : null;
    if (!$row) {
        return null;
    }
    $uid = (string) ($row['uid'] ?? '');
    if ($uid === '' || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $uid)) {
        return null;
    }
    return $row;
}

function live_cf_list_input_videos(string $inputUid): array
{
    if ($inputUid === '' || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $inputUid)) {
        return [];
    }
    $j = live_cf_api('stream/live_inputs/' . rawurlencode($inputUid) . '/videos');
    $rows = $j['result'] ?? null;
    return is_array($rows) ? $rows : [];
}

function live_cf_video_iframe(array $video): string
{
    $play = (string) ($video['playback']['hls'] ?? '');
    $preview = (string) ($video['preview'] ?? '');
    $uid = (string) ($video['uid'] ?? '');
    foreach ([$play, $preview] as $url) {
        if (function_exists('vod_cf_iframe_url')) {
            $iframe = vod_cf_iframe_url($url);
            if ($iframe !== '') {
                return $iframe;
            }
        }
        if (preg_match('#^(https://customer-[a-z0-9]+\.cloudflarestream\.com/[A-Za-z0-9_-]+)#i', $url, $m)) {
            return $m[1] . '/iframe';
        }
    }
    if ($uid !== '' && preg_match('/^[A-Za-z0-9_-]{16,64}$/', $uid)) {
        $host = live_cf_from_url(live_cf_url('cam', 'whep'))['host'];
        if ($host !== '') {
            return 'https://' . $host . '/' . $uid . '/iframe';
        }
    }
    return $play !== '' ? $play : $preview;
}

function live_cf_save_video_recording(PDO $pdo, array $room, array $video, string $kindLabel): bool
{
    $uid = (string) ($video['uid'] ?? '');
    if ($uid === '' || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $uid)) {
        return false;
    }
    $url = live_cf_video_iframe($video);
    if ($url === '') {
        return false;
    }
    $path = 'cf/' . $uid;
    $dup = $pdo->prepare('SELECT id FROM recordings WHERE video_path = ? OR video_url = ? LIMIT 1');
    $dup->execute([$path, $url]);
    if ($dup->fetch()) {
        return true;
    }
    $sec = (int) round((float) ($video['duration'] ?? 0));
    if ($sec < 15) {
        return false;
    }
    $sec = min(28800, max(1, $sec));
    $mins = max(1, min(480, (int) round($sec / 60)));
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
    if ($kindLabel !== '') {
        $label .= ' — ' . $kindLabel;
    }
    $label .= ' — ' . $when;
    if ($teacher !== '') {
        $label .= ' — ' . $teacher;
    }
    $isTest = function_exists('live_room_is_test') && live_room_is_test($room);
    if ($isTest && !str_starts_with($label, 'Test ·')) {
        $label = 'Test · ' . $label;
    }
    if (function_exists('ensure_recordings_duration_schema')) {
        ensure_recordings_duration_schema();
    }
    $title = function_exists('vod_clip_title') ? vod_clip_title($label) : mb_substr($label, 0, 180);
    try {
        $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, duration_sec, recorded_on, video_url, video_path, is_test) VALUES (?,?,?,?,?,CURDATE(),?,?,?)')
            ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $sec, $url, $path, $isTest ? 1 : 0]);
    } catch (Throwable $e) {
        try {
            $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, duration_sec, recorded_on, video_url, video_path) VALUES (?,?,?,?,?,CURDATE(),?,?)')
                ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $sec, $url, $path]);
        } catch (Throwable $e2) {
            try {
                $pdo->prepare('INSERT INTO recordings (group_id, teacher_id, title, mins, recorded_on, video_url, video_path) VALUES (?,?,?,?,CURDATE(),?,?)')
                    ->execute([(int) $room['group_id'], (int) $room['teacher_id'], $title, $mins, $url, $path]);
            } catch (Throwable $e3) {
                return false;
            }
        }
    }
    if (!$isTest && function_exists('notify_group_students')) {
        notify_group_students((int) $room['group_id'], 'Ders kaydı hazır', $label, url('ogrenci/kayitlar'));
    }
    return true;
}

function live_cf_import_room_recordings(PDO $pdo, array $room): int
{
    if (!live_cf_api_ready()) {
        return 0;
    }
    $id = (int) ($room['id'] ?? 0);
    if ($id > 0) {
        $local = $pdo->prepare('SELECT id FROM recordings WHERE video_path LIKE ? OR video_path = ? LIMIT 1');
        $local->execute(['vod/oda-' . $id . '-%', 'vod/live-' . $id . '.webm']);
        if ($local->fetch()) {
            return 0;
        }
    }
    $started = strtotime((string) ($room['started_at'] ?? '')) ?: 0;
    $ended = strtotime((string) ($room['ended_at'] ?? '')) ?: time();
    if ($started < 1 || ($ended - $started) > 8 * 3600) {
        $started = $ended - 4 * 3600;
    }
    $n = 0;
    foreach (['cam' => 'Kamera', 'screen' => 'Ekran'] as $kind => $label) {
        $uid = live_cf_from_url(live_cf_url($kind, 'whep'))['uid'];
        if ($uid === '') {
            $uid = live_cf_from_url(live_cf_url($kind, 'whip'))['uid'];
        }
        if ($uid === '') {
            continue;
        }
        foreach (live_cf_list_input_videos($uid) as $video) {
            if (!is_array($video)) {
                continue;
            }
            $state = (string) (($video['status']['state'] ?? '') ?: '');
            if ($state !== 'ready') {
                continue;
            }
            $created = strtotime((string) ($video['created'] ?? $video['uploaded'] ?? '')) ?: 0;
            if ($created > 0 && ($created < $started - 180 || $created > $ended + 900)) {
                continue;
            }
            if (live_cf_save_video_recording($pdo, $room, $video, $label)) {
                $n++;
            }
        }
    }
    return $n;
}

function live_cf_import_recent_rooms(PDO $pdo, ?int $teacherId = null): int
{
    if (!live_cf_api_ready()) {
        return 0;
    }
    try {
        $st = $pdo->query("SELECT * FROM live_rooms WHERE status = 'ended' AND (ended_at IS NULL OR ended_at >= (NOW() - INTERVAL 2 DAY)) ORDER BY id DESC LIMIT 20");
        $rooms = $st ? $st->fetchAll() : [];
    } catch (Throwable $e) {
        return 0;
    }
    $n = 0;
    foreach ($rooms as $room) {
        if ($teacherId !== null) {
            $tid = (int) ($room['teacher_id'] ?? 0);
            $gid = (int) ($room['group_id'] ?? 0);
            if ($tid !== $teacherId && !(function_exists('group_has_teacher') && group_has_teacher($gid, $teacherId))) {
                continue;
            }
        }
        try {
            $n += live_cf_import_room_recordings($pdo, $room);
        } catch (Throwable $e) {
        }
    }
    return $n;
}

function live_board_enabled(): bool
{
    return function_exists('setting_bool') && setting_bool('live_board_on', false);
}

function live_whip_url_ok(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) {
        return false;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (!str_contains($path, '/webRTC/')) {
        return false;
    }
    if (preg_match('/^customer-[a-z0-9]+\.cloudflarestream\.com$/', $host)) {
        return true;
    }
    foreach ([live_cf_url('cam', 'whip'), live_cf_url('screen', 'whip')] as $allow) {
        $ah = strtolower((string) parse_url($allow, PHP_URL_HOST));
        if ($ah !== '' && $ah === $host) {
            return true;
        }
    }
    return false;
}

function live_whip_abs_location(string $posted, string $loc): string
{
    $loc = trim($loc);
    if ($loc === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $loc)) {
        return $loc;
    }
    $parts = parse_url($posted);
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    if ($loc[0] === '/') {
        return $origin . $loc;
    }
    return $origin . '/' . ltrim($loc, '/');
}

function live_whip_loc_key(string $target): string
{
    $path = trim((string) parse_url($target, PHP_URL_PATH), '/');
    $id = explode('/', $path)[0] ?? 'x';
    return 'cf_whip_loc_' . preg_replace('/[^A-Za-z0-9_-]/', '', $id);
}

function live_whip_http(string $method, string $url, string $sdp = ''): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'sdp' => '', 'location' => '', 'error' => 'curl'];
    }
    $ch = curl_init($url);
    $headers = ['Accept: application/sdp'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/sdp';
        $opts[CURLOPT_POSTFIELDS] = $sdp;
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'sdp' => '', 'location' => '', 'error' => $err];
    }
    $head = substr($raw, 0, $hs);
    $body = (string) substr($raw, $hs);
    $loc = '';
    if (preg_match('/^Location:\s*(.+)$/im', $head, $m)) {
        $loc = live_whip_abs_location($url, trim($m[1]));
    }
    return [
        'ok' => $code >= 200 && $code < 300,
        'status' => $code,
        'sdp' => $body,
        'location' => $loc,
        'error' => '',
    ];
}

function live_merge_json_body(): void
{
    $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
    if (!str_contains($ct, 'application/json')) {
        return;
    }
    $raw = file_get_contents('php://input');
    $j = json_decode((string) $raw, true);
    if (!is_array($j)) {
        return;
    }
    foreach ($j as $k => $v) {
        if (!is_string($k) || $k === '') {
            continue;
        }
        if (is_bool($v)) {
            $_POST[$k] = $v ? '1' : '0';
        } elseif (is_int($v) || is_float($v)) {
            $_POST[$k] = (string) $v;
        } elseif (is_string($v)) {
            $_POST[$k] = $v;
        }
    }
}

function live_request_sdp(): string
{
    return (string) ($_POST['sdp'] ?? '');
}

function live_whip_proxy(string $method, string $target, string $sdp = ''): array
{
    $method = strtoupper($method) === 'DELETE' ? 'DELETE' : 'POST';
    if (!live_whip_url_ok($target)) {
        return ['ok' => false, 'status' => 400, 'sdp' => '', 'location' => '', 'error' => 'url'];
    }
    $key = live_whip_loc_key($target);
    $prev = (string) ($_SESSION[$key] ?? '');
    if ($method === 'DELETE') {
        $res = live_whip_http('DELETE', $target);
        if ($prev !== '' && $prev !== $target && live_whip_url_ok($prev)) {
            live_whip_http('DELETE', $prev);
        }
        unset($_SESSION[$key]);
        return $res;
    }
    $res = live_whip_http('POST', $target, $sdp);
    if ((int) ($res['status'] ?? 0) === 409) {
        if ($prev !== '' && live_whip_url_ok($prev)) {
            live_whip_http('DELETE', $prev);
            unset($_SESSION[$key]);
        }
        usleep(700000);
        $res = live_whip_http('POST', $target, $sdp);
        if ((int) ($res['status'] ?? 0) === 409) {
            usleep(1100000);
            $res = live_whip_http('POST', $target, $sdp);
        }
    }
    if (!empty($res['location']) && live_whip_url_ok((string) $res['location'])) {
        $_SESSION[$key] = (string) $res['location'];
    }
    return $res;
}

function live_hls_url(string $streamKey, int $which = 0): string
{
    if (live_cf_ready()) {
        return $which > 0 ? '' : live_cf_hls_url(live_cf_kind($streamKey));
    }
    $paths = live_stream_paths($streamKey);
    if (!isset($paths[$which])) {
        return '';
    }
    return live_hls_base() . '/' . $paths[$which] . '/index.m3u8?cookieCheck=1';
}

function live_whep_url(string $streamKey, int $which = 0): string
{
    if (live_cf_ready()) {
        if ($which > 0) {
            return '';
        }
        $url = live_cf_url(live_cf_kind($streamKey), 'whep');
        return $url !== '' ? $url : '';
    }
    $paths = live_stream_paths($streamKey);
    if (!isset($paths[$which])) {
        return '';
    }
    return live_whep_base() . '/' . $paths[$which] . '/whep';
}

function live_whip_url(string $streamKey, int $which = 0): string
{
    if (live_cf_ready()) {
        if ($which > 0) {
            return '';
        }
        return live_cf_url(live_cf_kind($streamKey), 'whip');
    }
    $paths = live_stream_paths($streamKey);
    if (!isset($paths[$which])) {
        return '';
    }
    return live_whep_base() . '/' . $paths[$which] . '/whip';
}

function live_normalize_play_mode(?string $mode = null): string
{
    return 'browser';
}

function live_last_play_mode(): string
{
    return 'browser';
}

function live_remember_play_mode(string $mode = 'browser'): string
{
    $_SESSION['live_play_mode'] = 'browser';
    return 'browser';
}

function ensure_live_play_mode_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $has = false;
        foreach (db()->query('SHOW COLUMNS FROM live_rooms')->fetchAll() as $row) {
            if (($row['Field'] ?? '') === 'play_mode') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            db()->exec("ALTER TABLE live_rooms ADD COLUMN play_mode VARCHAR(20) NOT NULL DEFAULT 'browser' AFTER stream_key");
        }
    } catch (Throwable $e) {
        $done = false;
    }
    ensure_live_access_schema();
}

function ensure_live_access_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM live_rooms')->fetchAll() as $row) {
            $cols[(string) ($row['Field'] ?? '')] = true;
        }
        if (empty($cols['access_mode'])) {
            db()->exec("ALTER TABLE live_rooms ADD COLUMN access_mode VARCHAR(12) NOT NULL DEFAULT 'all'");
        }
        if (empty($cols['allow_student_ids'])) {
            db()->exec('ALTER TABLE live_rooms ADD COLUMN allow_student_ids TEXT NULL');
        }
        if (empty($cols['screen_on'])) {
            db()->exec('ALTER TABLE live_rooms ADD COLUMN screen_on TINYINT NOT NULL DEFAULT 0');
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function live_room_set_screen(PDO $pdo, int $roomId, bool $on): void
{
    ensure_live_access_schema();
    $v = $on ? 1 : 0;
    try {
        $pdo->prepare('UPDATE live_rooms SET screen_on=? WHERE id=?')->execute([$v, $roomId]);
    } catch (Throwable $e) {
    }
    try {
        if (function_exists('live_board_save')) {
            live_board_save($pdo, $roomId, ['screen' => $v]);
        }
    } catch (Throwable $e) {
    }
}

function live_room_is_test(array $room): bool
{
    return ($room['access_mode'] ?? 'all') === 'allow';
}

function live_allow_ids(array $room): array
{
    $raw = trim((string) ($room['allow_student_ids'] ?? ''));
    if ($raw === '') {
        return [];
    }
    $j = json_decode($raw, true);
    if (is_array($j)) {
        return array_values(array_unique(array_filter(array_map('intval', $j))));
    }
    return array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
}

function live_student_on_allowlist(array $room, int $studentId): bool
{
    if ($studentId < 1) {
        return false;
    }
    return in_array($studentId, live_allow_ids($room), true);
}

function live_student_live_rooms(int $studentId): array
{
    ensure_live_access_schema();
    if ($studentId < 1) {
        return [];
    }
    $cols = live_enrollment_columns();
    $sql = "SELECT r.*, t.name teacher_name FROM live_rooms r
         JOIN users t ON t.id = r.teacher_id
         JOIN enrollments e ON e.group_id = r.group_id AND e.student_id = ?";
    if (isset($cols['status'])) {
        $sql .= " AND (e.status = 'aktif' OR e.status IS NULL)";
    }
    if (isset($cols['expires_at'])) {
        $sql .= ' AND (e.expires_at IS NULL OR e.expires_at > NOW())';
    }
    $sql .= " WHERE r.status = 'live' ORDER BY r.id";
    try {
        $st = db()->prepare($sql);
        $st->execute([$studentId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            if (live_student_watch_reason($r, $studentId) === null) {
                $out[] = $r;
            }
        }
        return $out;
    } catch (Throwable) {
        return [];
    }
}

function live_nav_open_count(string $role, int $userId = 0): int
{
    ensure_live_access_schema();
    try {
        if ($role === 'ogrenci' && $userId > 0) {
            return count(live_student_live_rooms($userId));
        }
        if ($role === 'ogretmen' && $userId > 0) {
            $st = db()->prepare("SELECT COUNT(*) FROM live_rooms WHERE status = 'live' AND teacher_id = ?");
            $st->execute([$userId]);
            return (int) $st->fetchColumn();
        }
        return (int) db()->query("SELECT COUNT(*) FROM live_rooms WHERE status = 'live'")->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function live_room_play_mode(array $room): string
{
    ensure_live_play_mode_schema();
    return live_normalize_play_mode($room['play_mode'] ?? null);
}

function live_start_chat_message(string $mode = 'browser'): string
{
    return 'Oda açıldı.';
}

function ensure_live_attendance_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $has = false;
        foreach (db()->query('SHOW COLUMNS FROM attendance')->fetchAll() as $row) {
            if (($row['Field'] ?? '') === 'entered_at') {
                $has = true;
                break;
            }
        }
        if (!$has) {
            db()->exec('ALTER TABLE attendance ADD COLUMN entered_at DATETIME NULL AFTER present');
        }
    } catch (Throwable $e) {
        $done = false;
    }
    ensure_live_log_schema();
    ensure_live_access_schema();
}

function ensure_live_log_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS live_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_id INT UNSIGNED NOT NULL DEFAULT 0,
                user_id INT UNSIGNED NULL,
                kind VARCHAR(40) NOT NULL,
                message VARCHAR(255) NOT NULL,
                detail VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY room_created (room_id, created_at),
                KEY kind_created (kind, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    } catch (Throwable $e) {
        $done = false;
    }
}

function live_log_is_alert(string $kind): bool
{
    return in_array($kind, ['whip_fail', 'whip_409', 'whep_fail', 'camera', 'ice'], true);
}

function live_log_event(int $roomId, string $kind, string $message, string $detail = '', ?int $userId = null): void
{
    ensure_live_log_schema();
    $kind = preg_replace('/[^a-z0-9_]/', '', strtolower($kind)) ?: 'info';
    $message = mb_substr(trim($message), 0, 255);
    $detail = mb_substr(trim($detail), 0, 500);
    if ($message === '') {
        return;
    }
    try {
        db()->prepare('INSERT INTO live_logs (room_id, user_id, kind, message, detail) VALUES (?,?,?,?,?)')
            ->execute([$roomId, $userId, $kind, $message, $detail !== '' ? $detail : null]);
    } catch (Throwable $e) {
        return;
    }
    if (!live_log_is_alert($kind)) {
        return;
    }
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM live_logs WHERE room_id = ? AND kind = ? AND created_at > DATE_SUB(NOW(), INTERVAL 3 MINUTE)');
        $st->execute([$roomId, $kind]);
        if ((int) $st->fetchColumn() !== 1) {
            return;
        }
    } catch (Throwable $e) {
        return;
    }
    $link = function_exists('url') ? url('admin/canli-log.php' . ($roomId > 0 ? '?oda=' . $roomId : '')) : '';
    try {
        $admins = db()->query("SELECT id FROM users WHERE role = 'admin' AND status IN ('aktif','bekliyor')")->fetchAll();
        foreach ($admins as $admin) {
            if (function_exists('notify_user')) {
                notify_user((int) $admin['id'], 'Canlı ders hatası', $message, $link);
            }
        }
    } catch (Throwable $e) {
    }
}

function live_logs_recent(int $roomId = 0, int $limit = 200): array
{
    ensure_live_log_schema();
    $limit = max(1, min(500, $limit));
    try {
        if ($roomId > 0) {
            $st = db()->prepare('SELECT l.*, r.title room_title, u.name user_name
                FROM live_logs l
                LEFT JOIN live_rooms r ON r.id = l.room_id
                LEFT JOIN users u ON u.id = l.user_id
                WHERE l.room_id = ?
                ORDER BY l.id DESC LIMIT ' . $limit);
            $st->execute([$roomId]);
            return $st->fetchAll();
        }
        return db()->query(
            'SELECT l.*, r.title room_title, u.name user_name
             FROM live_logs l
             LEFT JOIN live_rooms r ON r.id = l.room_id
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC LIMIT ' . $limit
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function live_log_recent_error_count(int $minutes = 120): int
{
    ensure_live_log_schema();
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM live_logs WHERE kind IN ('whip_fail','whip_409','whep_fail','camera','ice') AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $st->execute([max(5, $minutes)]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function ensure_live_pause_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM live_rooms')->fetchAll() as $row) {
            $cols[(string) ($row['Field'] ?? '')] = true;
        }
        if (!isset($cols['paused'])) {
            db()->exec('ALTER TABLE live_rooms ADD COLUMN paused TINYINT(1) NOT NULL DEFAULT 0 AFTER broadcasting');
        }
        if (!isset($cols['pause_ends_at'])) {
            db()->exec('ALTER TABLE live_rooms ADD COLUMN pause_ends_at DATETIME NULL AFTER paused');
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function live_room_pause_state(array &$room): array
{
    ensure_live_pause_schema();
    $paused = !empty($room['paused']);
    return [
        'paused' => $paused ? 1 : 0,
    ];
}

function ensure_live_board_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS live_board (
              room_id INT UNSIGNED PRIMARY KEY,
              pdf_path VARCHAR(255) NOT NULL DEFAULT \'\',
              page INT UNSIGNED NOT NULL DEFAULT 1,
              pages INT UNSIGNED NOT NULL DEFAULT 0,
              zoom DECIMAL(5,2) NOT NULL DEFAULT 1,
              pan_x DECIMAL(6,3) NOT NULL DEFAULT 0,
              pan_y DECIMAL(6,3) NOT NULL DEFAULT 0,
              strokes MEDIUMTEXT NOT NULL,
              rev INT UNSIGNED NOT NULL DEFAULT 0,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB'
        );
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM live_board')->fetchAll() as $col) {
            $cols[(string) $col['Field']] = true;
        }
        if (!isset($cols['zoom'])) {
            db()->exec('ALTER TABLE live_board ADD COLUMN zoom DECIMAL(5,2) NOT NULL DEFAULT 1');
        }
        if (!isset($cols['pan_x'])) {
            db()->exec('ALTER TABLE live_board ADD COLUMN pan_x DECIMAL(6,3) NOT NULL DEFAULT 0');
        }
        if (!isset($cols['pan_y'])) {
            db()->exec('ALTER TABLE live_board ADD COLUMN pan_y DECIMAL(6,3) NOT NULL DEFAULT 0');
        }
        if (!isset($cols['screen'])) {
            db()->exec('ALTER TABLE live_board ADD COLUMN screen TINYINT NOT NULL DEFAULT 0');
        }
        if (!isset($cols['board_full'])) {
            db()->exec('ALTER TABLE live_board ADD COLUMN board_full TINYINT NOT NULL DEFAULT 0');
        }
        if (!isset($cols['cam_x'])) {
            db()->exec('ALTER TABLE live_board ADD COLUMN cam_x DECIMAL(5,4) NOT NULL DEFAULT 0.7200');
        }
        if (!isset($cols['cam_y'])) {
            db()->exec('ALTER TABLE live_board ADD COLUMN cam_y DECIMAL(5,4) NOT NULL DEFAULT 0.0400');
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function live_board_row(PDO $pdo, int $roomId): array
{
    ensure_live_board_schema();
    $st = $pdo->prepare('SELECT * FROM live_board WHERE room_id = ?');
    $st->execute([$roomId]);
    $row = $st->fetch();
    if ($row) {
        return $row;
    }
    $pdo->prepare('INSERT INTO live_board (room_id, strokes) VALUES (?,?)')->execute([$roomId, '{}']);
    $st->execute([$roomId]);
    return $st->fetch() ?: [
        'room_id' => $roomId,
        'pdf_path' => '',
        'page' => 1,
        'pages' => 0,
        'strokes' => '{}',
        'rev' => 0,
    ];
}

function live_board_public(array $row, int $roomId): array
{
    $pdf = trim((string) ($row['pdf_path'] ?? ''));
    $strokes = json_decode((string) ($row['strokes'] ?? '{}'), true);
    if (!is_array($strokes)) {
        $strokes = [];
    }
    return [
        'rev' => (int) ($row['rev'] ?? 0),
        'page' => max(1, (int) ($row['page'] ?? 1)),
        'pages' => max(0, (int) ($row['pages'] ?? 0)),
        'pdf' => $pdf !== '' ? url('api/dosya.php') . '?tur=tahta&id=' . $roomId . '&v=' . rawurlencode(basename($pdf)) : '',
        'strokes' => $strokes,
        'zoom' => max(0.08, min(40, (float) ($row['zoom'] ?? 1))),
        'panX' => max(-200, min(200, (float) ($row['pan_x'] ?? 0))),
        'panY' => max(-200, min(200, (float) ($row['pan_y'] ?? 0))),
        'screen' => !empty($row['screen']) ? 1 : 0,
        'boardFull' => !empty($row['board_full']) ? 1 : 0,
        'camX' => max(0, min(1, (float) ($row['cam_x'] ?? 0.72))),
        'camY' => max(0, min(1, (float) ($row['cam_y'] ?? 0.04))),
    ];
}

function live_board_save(PDO $pdo, int $roomId, array $fields): array
{
    $row = live_board_row($pdo, $roomId);
    $pdf = array_key_exists('pdf_path', $fields) ? (string) $fields['pdf_path'] : (string) $row['pdf_path'];
    $page = array_key_exists('page', $fields) ? max(1, (int) $fields['page']) : max(1, (int) $row['page']);
    $pages = array_key_exists('pages', $fields) ? max(0, (int) $fields['pages']) : max(0, (int) $row['pages']);
    $strokes = array_key_exists('strokes', $fields) ? (string) $fields['strokes'] : (string) $row['strokes'];
    $zoom = array_key_exists('zoom', $fields) ? max(0.08, min(40, (float) $fields['zoom'])) : max(0.08, min(40, (float) ($row['zoom'] ?? 1)));
    $panX = array_key_exists('pan_x', $fields) ? max(-200, min(200, (float) $fields['pan_x'])) : max(-200, min(200, (float) ($row['pan_x'] ?? 0)));
    $panY = array_key_exists('pan_y', $fields) ? max(-200, min(200, (float) $fields['pan_y'])) : max(-200, min(200, (float) ($row['pan_y'] ?? 0)));
    $screen = array_key_exists('screen', $fields) ? ((int) $fields['screen'] ? 1 : 0) : ((int) ($row['screen'] ?? 0) ? 1 : 0);
    $boardFull = array_key_exists('board_full', $fields) ? ((int) $fields['board_full'] ? 1 : 0) : ((int) ($row['board_full'] ?? 0) ? 1 : 0);
    $camX = array_key_exists('cam_x', $fields) ? max(0, min(1, (float) $fields['cam_x'])) : max(0, min(1, (float) ($row['cam_x'] ?? 0.72)));
    $camY = array_key_exists('cam_y', $fields) ? max(0, min(1, (float) $fields['cam_y'])) : max(0, min(1, (float) ($row['cam_y'] ?? 0.04)));
    $rev = (int) ($row['rev'] ?? 0) + 1;
    try {
        $pdo->prepare('UPDATE live_board SET pdf_path=?, page=?, pages=?, zoom=?, pan_x=?, pan_y=?, screen=?, board_full=?, cam_x=?, cam_y=?, strokes=?, rev=? WHERE room_id=?')
            ->execute([$pdf, $page, $pages, $zoom, $panX, $panY, $screen, $boardFull, $camX, $camY, $strokes, $rev, $roomId]);
    } catch (Throwable $e) {
        try {
            $pdo->prepare('UPDATE live_board SET pdf_path=?, page=?, pages=?, zoom=?, pan_x=?, pan_y=?, screen=?, strokes=?, rev=? WHERE room_id=?')
                ->execute([$pdf, $page, $pages, $zoom, $panX, $panY, $screen, $strokes, $rev, $roomId]);
        } catch (Throwable $e2) {
            $pdo->prepare('UPDATE live_board SET pdf_path=?, page=?, pages=?, strokes=?, rev=? WHERE room_id=?')
                ->execute([$pdf, $page, $pages, $strokes, $rev, $roomId]);
        }
    }
    return live_board_row($pdo, $roomId);
}

function live_board_parse_stroke($raw): ?array
{
    if (!is_array($raw)) {
        return null;
    }
    $type = ($raw['t'] ?? '') === 'erase' ? 'erase' : 'pen';
    $color = (string) ($raw['c'] ?? '#111827');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $color = '#111827';
    }
    $width = (float) ($raw['w'] ?? 3);
    $width = max(1, min(28, $width));
    $pts = $raw['p'] ?? [];
    if (!is_array($pts) || count($pts) < 1 || count($pts) > 2500) {
        return null;
    }
    $ver = (int) ($raw['v'] ?? 1);
    $out = [];
    foreach ($pts as $pt) {
        if (!is_array($pt) || !isset($pt[0], $pt[1])) {
            continue;
        }
        if ($ver >= 2) {
            $x = max(-0.25, min(1.25, (float) $pt[0]));
            $y = max(-4, min(160, (float) $pt[1]));
        } else {
            $x = max(0, min(1, (float) $pt[0]));
            $y = max(0, min(1, (float) $pt[1]));
        }
        $pr = isset($pt[2]) ? max(0.05, min(1, (float) $pt[2])) : 1;
        $out[] = [round($x, 5), round($y, 5), round($pr, 3)];
        if (count($out) >= 2500) {
            break;
        }
    }
    if ($out === []) {
        return null;
    }
    $stroke = ['t' => $type, 'c' => $color, 'w' => $width, 'p' => $out];
    if ($ver >= 2) {
        $stroke['v'] = 2;
    }
    return $stroke;
}

function live_play_mode_picker(string $name = 'play_mode', ?string $selected = null, string $layout = 'cards'): string
{
    return '';
}

function live_health_url(): string
{
    if (live_cf_ready()) {
        return '';
    }
    return rtrim(live_hls_base(), '/') . '/';
}

function live_new_stream_key(PDO $pdo): string
{
    for ($i = 0; $i < 10; $i++) {
        $key = 'oda-' . bin2hex(random_bytes(8));
        $st = $pdo->prepare('SELECT id FROM live_rooms WHERE stream_key = ?');
        $st->execute([$key]);
        if (!$st->fetch()) {
            return $key;
        }
    }
    return 'oda-' . bin2hex(random_bytes(16));
}

function live_ensure_stream_key(PDO $pdo, array $room): string
{
    $key = trim((string) ($room['stream_key'] ?? ''));
    if ($key !== '') {
        return $key;
    }
    $key = live_new_stream_key($pdo);
    $pdo->prepare('UPDATE live_rooms SET stream_key = ? WHERE id = ?')->execute([$key, (int) $room['id']]);
    return $key;
}

function live_enrollment_columns(): array
{
    static $cols = null;
    if (is_array($cols)) {
        return $cols;
    }
    $cols = [];
    foreach (db()->query('SHOW COLUMNS FROM enrollments')->fetchAll() as $row) {
        $cols[(string) $row['Field']] = true;
    }
    return $cols;
}

function live_student_enrolled(int $studentId, int $groupId): bool
{
    $cols = live_enrollment_columns();
    $sql = 'SELECT id FROM enrollments WHERE student_id = ? AND group_id = ?';
    if (isset($cols['status'])) {
        $sql .= " AND (status = 'aktif' OR status IS NULL)";
    }
    if (isset($cols['expires_at'])) {
        $sql .= ' AND (expires_at IS NULL OR expires_at > NOW())';
    }
    $sql .= ' LIMIT 1';
    $st = db()->prepare($sql);
    $st->execute([$studentId, $groupId]);
    return (bool) $st->fetch();
}

function live_user_can_access(array $u, array $room): bool
{
    if ($u['role'] === 'admin') {
        return true;
    }
    if ($u['role'] === 'ogretmen') {
        return (int) $room['teacher_id'] === (int) $u['id'];
    }
    if ($u['role'] === 'ogrenci') {
        if (($u['status'] ?? '') !== 'aktif') {
            return false;
        }
        if (!live_student_enrolled((int) $u['id'], (int) $room['group_id'])) {
            return false;
        }
        if (live_room_is_test($room) && !live_student_on_allowlist($room, (int) $u['id'])) {
            return false;
        }
        return true;
    }
    return false;
}

function live_user_can_publish(array $u, array $room): bool
{
    return (int) ($room['teacher_id'] ?? 0) === (int) ($u['id'] ?? 0);
}

function live_user_is_observer(array $u, array $room): bool
{
    return ($u['role'] ?? '') === 'admin' && !live_user_can_publish($u, $room);
}

function live_full_watch_message(): string
{
    return 'Kontenjan dolmuştur. Kayıtlardan izleyebilirsiniz.';
}

function live_group_cap(int $groupId): int
{
    if ($groupId < 1) {
        return 1;
    }
    $st = db()->prepare('SELECT cap FROM class_groups WHERE id = ?');
    $st->execute([$groupId]);
    return max(1, (int) $st->fetchColumn());
}

function live_present_count(int $roomId): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM attendance WHERE room_id = ? AND present = 1');
        $st->execute([$roomId]);
        return (int) $st->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function live_present_students(int $roomId): array
{
    ensure_live_attendance_schema();
    try {
        $st = db()->prepare(
            'SELECT u.id, u.name, a.entered_at
             FROM attendance a
             JOIN users u ON u.id = a.student_id
             WHERE a.room_id = ? AND a.present = 1
             ORDER BY a.entered_at IS NULL, a.entered_at ASC, u.name'
        );
        $st->execute([$roomId]);
        return $st->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function live_entered_students(int $roomId): array
{
    ensure_live_attendance_schema();
    try {
        $st = db()->prepare(
            'SELECT u.id, u.name, u.email, a.present, a.entered_at
             FROM attendance a
             JOIN users u ON u.id = a.student_id
             WHERE a.room_id = ? AND (a.present = 1 OR a.entered_at IS NOT NULL)
             ORDER BY a.entered_at IS NULL, a.entered_at ASC, a.id ASC'
        );
        $st->execute([$roomId]);
        return $st->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function live_room_attendance_roster(int $roomId, int $groupId): array
{
    ensure_live_attendance_schema();
    try {
        $st = db()->prepare(
            'SELECT u.id, u.name, u.email, u.phone,
                    COALESCE(a.present, 0) AS present, a.entered_at
             FROM enrollments e
             JOIN users u ON u.id = e.student_id
             LEFT JOIN attendance a ON a.room_id = ? AND a.student_id = u.id
             WHERE e.group_id = ?
             ORDER BY (a.entered_at IS NULL AND COALESCE(a.present, 0) = 0), a.entered_at ASC, u.name'
        );
        $st->execute([$roomId, $groupId]);
        return $st->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function live_attendance_status_label(array $row, bool $live = false): string
{
    $in = !empty($row['entered_at']) || (int) ($row['present'] ?? 0) === 1;
    if (!$in) {
        return 'Girmedi';
    }
    if ($live && (int) ($row['present'] ?? 0) === 1) {
        return 'Derste';
    }
    if ($live) {
        return 'Çıktı';
    }
    return 'Katıldı';
}

function live_teacher_can_see_room(array $room, int $teacherId): bool
{
    if ($teacherId < 1) {
        return false;
    }
    if ((int) ($room['teacher_id'] ?? 0) === $teacherId) {
        return true;
    }
    return function_exists('group_has_teacher') && group_has_teacher((int) ($room['group_id'] ?? 0), $teacherId);
}

function live_student_has_seat(int $roomId, int $studentId): bool
{
    try {
        $st = db()->prepare('SELECT present FROM attendance WHERE room_id = ? AND student_id = ?');
        $st->execute([$roomId, $studentId]);
        $row = $st->fetch();
        return $row && (int) $row['present'] === 1;
    } catch (Throwable) {
        return false;
    }
}

function live_video_only_message(): string
{
    return 'Bu paket yalnızca kayıt izleme içindir. Kayıtlardan izleyebilirsiniz.';
}

function live_student_watch_reason(array $room, int $studentId): ?string
{
    $groupId = (int) ($room['group_id'] ?? 0);
    if ($studentId < 1 || $groupId < 1) {
        return live_full_watch_message();
    }
    if (live_room_is_test($room)) {
        if (!live_student_on_allowlist($room, $studentId)) {
            return 'Bu yayın bir testtir. Yalnız davet edilen öğrenciler girebilir.';
        }
        return null;
    }
    if (function_exists('student_can_join_live') && !student_can_join_live($studentId, $groupId)) {
        return live_video_only_message();
    }
    if (!live_student_room_open($room, $studentId)) {
        return live_full_watch_message();
    }
    return null;
}

function live_student_room_open(array $room, int $studentId): bool
{
    $roomId = (int) ($room['id'] ?? 0);
    $groupId = (int) ($room['group_id'] ?? 0);
    if ($roomId < 1 || $studentId < 1) {
        return false;
    }
    if (live_student_has_seat($roomId, $studentId)) {
        return true;
    }
    return live_present_count($roomId) < live_group_cap($groupId);
}

function live_mark_student_present(int $roomId, int $studentId): void
{
    ensure_live_attendance_schema();
    db()->prepare(
        'INSERT INTO attendance (room_id, student_id, present, entered_at) VALUES (?,?,1,NOW())
         ON DUPLICATE KEY UPDATE present = 1, entered_at = IF(entered_at IS NULL, NOW(), entered_at)'
    )->execute([$roomId, $studentId]);
}

function live_mark_student_absent(int $roomId, int $studentId): void
{
    db()->prepare('UPDATE attendance SET present = 0 WHERE room_id = ? AND student_id = ?')
        ->execute([$roomId, $studentId]);
}

function live_student_try_enter(array $room, int $studentId): bool
{
    $roomId = (int) ($room['id'] ?? 0);
    $groupId = (int) ($room['group_id'] ?? 0);
    if ($roomId < 1 || $groupId < 1 || $studentId < 1) {
        return false;
    }
    ensure_live_attendance_schema();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (live_room_is_test($room)) {
            if (!live_student_on_allowlist($room, $studentId)) {
                $pdo->rollBack();
                return false;
            }
            live_mark_student_present($roomId, $studentId);
            $pdo->commit();
            return true;
        }
        $lock = $pdo->prepare('SELECT cap FROM class_groups WHERE id = ? FOR UPDATE');
        $lock->execute([$groupId]);
        $row = $lock->fetch();
        if ($row === false) {
            $pdo->rollBack();
            return false;
        }
        $cap = max(1, (int) ($row['cap'] ?? 1));
        if (live_student_has_seat($roomId, $studentId)) {
            $pdo->commit();
            return true;
        }
        if (live_present_count($roomId) >= $cap) {
            $pdo->rollBack();
            return false;
        }
        live_mark_student_present($roomId, $studentId);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function live_watch_recordings_url(int $groupId): string
{
    return url('ogrenci/kayitlar.php' . ($groupId > 0 ? '?grup=' . $groupId : ''));
}

function live_full_watch_html(int $groupId, string $extraClass = ''): string
{
    $cls = trim('mt-2 text-sm font-bold text-accent ' . $extraClass);
    return '<p class="' . e($cls) . '">' . e(live_full_watch_message()) . '</p>'
        . '<a class="btn-outline mt-2 inline-flex text-sm" href="' . e(live_watch_recordings_url($groupId)) . '">Kayıtlara git</a>';
}

function live_public_room(array $room): array
{
    $pause = live_room_pause_state($room);
    return [
        'id' => (int) $room['id'],
        'title' => (string) $room['title'],
        'topic' => (string) $room['topic'],
        'status' => (string) $room['status'],
        'teacher_id' => (int) $room['teacher_id'],
        'group_id' => (int) $room['group_id'],
        'broadcasting' => (int) ($room['broadcasting'] ?? 0),
        'paused' => (int) $pause['paused'],
        'started_at' => (string) ($room['started_at'] ?? ''),
        'play_mode' => live_room_play_mode($room),
        'screen' => (int) ($room['screen_on'] ?? 0),
    ];
}
