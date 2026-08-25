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
    ];
}
