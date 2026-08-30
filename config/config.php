<?php
function oi_env(string $key, string $default = ''): string
{
    $v = getenv($key);
    if ($v === false) {
        $raw = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        $v = is_string($raw) ? $raw : $default;
    }
    return $v;
}

define('DB_HOST', oi_env('DB_HOST', '127.0.0.1'));
define('DB_NAME', oi_env('DB_NAME', 'online_ilahiyat'));
define('DB_USER', oi_env('DB_USER', 'root'));
define('DB_PASS', oi_env('DB_PASS', ''));
define('BASE_URL', oi_env('BASE_URL', '/online-ilahiyat'));
define('APP_NAME', 'Online İlahiyat');
// WebRTC ICE / MediaMTX ek host. Boşsa admin live_host, o da boşsa sayfa host'u.
define('LIVE_HOST', oi_env('LIVE_HOST', ''));
// HTTPS alt alanlar (Coolify). Boşsa yerel http://HOST:8888 / :8889 kullanılır.
define('LIVE_HLS_BASE', rtrim(oi_env('LIVE_HLS_BASE', ''), '/'));
define('LIVE_WHEP_BASE', rtrim(oi_env('LIVE_WHEP_BASE', ''), '/'));
