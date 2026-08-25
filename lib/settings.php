<?php
function settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($reload) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db()->query('SELECT k, v FROM settings') as $row) {
            $cache[$row['k']] = $row['v'];
        }
    } catch (Throwable) {
        $cache = [];
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    $v = array_key_exists($key, $all) ? (string) $all[$key] : $default;
    if ($v !== '' && function_exists('utf8_from_mojibake')) {
        $v = utf8_from_mojibake($v);
    }
    return $v;
}

function setting_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$key, $value]);
    settings_all(true);
}

function setting_bool(string $key, bool $default = false): bool
{
    $v = setting($key, $default ? '1' : '0');
    return $v === '1' || $v === 'true' || $v === 'on';
}

function app_public_url(string $path = ''): string
{
    $base = rtrim(setting('site_url'), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = ($https ? 'https' : 'http') . '://' . $host . rtrim(BASE_URL, '/');
    }
    $path = ltrim($path, '/');
    if (function_exists('pretty_path')) {
        $path = pretty_path($path);
    }
    return $path === '' ? $base : $base . '/' . $path;
}
