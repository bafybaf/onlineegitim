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
    return ['live/' . $enc, $enc];
}

function live_rtmp_url(): string
{
    return 'rtmp://' . live_obs_host() . ':1935/live';
}

function live_hls_url(string $streamKey, int $which = 0): string
{
    $paths = live_stream_paths($streamKey);
    if (!isset($paths[$which])) {
        return '';
    }
    return live_hls_base() . '/' . $paths[$which] . '/index.m3u8?cookieCheck=1';
}

function live_whep_url(string $streamKey, int $which = 0): string
{
    $paths = live_stream_paths($streamKey);
    if (!isset($paths[$which])) {
        return '';
    }
    return live_whep_base() . '/' . $paths[$which] . '/whep';
}

function live_whip_url(string $streamKey, int $which = 0): string
{
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
    $rev = (int) ($row['rev'] ?? 0) + 1;
    try {
        $pdo->prepare('UPDATE live_board SET pdf_path=?, page=?, pages=?, zoom=?, pan_x=?, pan_y=?, screen=?, strokes=?, rev=? WHERE room_id=?')
            ->execute([$pdf, $page, $pages, $zoom, $panX, $panY, $screen, $strokes, $rev, $roomId]);
    } catch (Throwable $e) {
        $pdo->prepare('UPDATE live_board SET pdf_path=?, page=?, pages=?, strokes=?, rev=? WHERE room_id=?')
            ->execute([$pdf, $page, $pages, $strokes, $rev, $roomId]);
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
    if (!is_array($pts) || count($pts) < 1 || count($pts) > 400) {
        return null;
    }
    $out = [];
    foreach ($pts as $pt) {
        if (!is_array($pt) || !isset($pt[0], $pt[1])) {
            continue;
        }
        $x = max(0, min(1, (float) $pt[0]));
        $y = max(0, min(1, (float) $pt[1]));
        $pr = isset($pt[2]) ? max(0.05, min(1, (float) $pt[2])) : 1;
        $out[] = [round($x, 4), round($y, 4), round($pr, 3)];
        if (count($out) >= 400) {
            break;
        }
    }
    if ($out === []) {
        return null;
    }
    return ['t' => $type, 'c' => $color, 'w' => $width, 'p' => $out];
}

function live_play_mode_picker(string $name = 'play_mode', ?string $selected = null, string $layout = 'cards'): string
{
    return '';
}

function live_health_url(): string
{
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
        return live_student_enrolled((int) $u['id'], (int) $room['group_id']);
    }
    return false;
}

function live_user_can_publish(array $u, array $room): bool
{
    return $u['role'] === 'admin' || (int) $room['teacher_id'] === (int) $u['id'];
}

function live_public_room(array $room): array
{
    return [
        'id' => (int) $room['id'],
        'title' => (string) $room['title'],
        'topic' => (string) $room['topic'],
        'status' => (string) $room['status'],
        'teacher_id' => (int) $room['teacher_id'],
        'group_id' => (int) $room['group_id'],
        'broadcasting' => (int) ($room['broadcasting'] ?? 0),
        'started_at' => (string) ($room['started_at'] ?? ''),
        'play_mode' => live_room_play_mode($room),
    ];
}
