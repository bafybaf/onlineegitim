<?php

function security_https(): bool
{
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($fwd === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
}

function security_is_local(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host) ?: $host;
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function security_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function security_request_host(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return preg_replace('/:\d+$/', '', $host) ?: $host;
}

function security_configure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $path = rtrim((string) BASE_URL, '/');
    if ($path === '') {
        $path = '/';
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('oi_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $path,
        'secure' => security_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function security_apply_runtime(): void
{
    if (!security_is_local()) {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL);
    }
    header_remove('X-Powered-By');
}

function security_apply_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(self), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (security_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function security_csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    $t = htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf" value="' . $t . '">';
}

function security_html_head(): void
{
    $t = htmlspecialchars(security_csrf_token(), ENT_QUOTES, 'UTF-8');
    echo '<meta name="csrf-token" content="' . $t . '" />' . "\n";
    echo '<script>(function(){var t=' . json_encode(security_csrf_token()) . ';document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("form").forEach(function(f){var method=(f.getAttribute("method")||"get").toLowerCase();if(method!=="post")return;if(f.querySelector(\'input[name="_csrf"]\'))return;var i=document.createElement("input");i.type="hidden";i.name="_csrf";i.value=t;f.appendChild(i);});});if(window.fetch){var orig=window.fetch;window.fetch=function(input,init){init=init||{};var method=String(init.method||(input&&input.method)||"GET").toUpperCase();var href="";try{href=typeof input==="string"?input:(input&&input.url)||"";href=new URL(href,location.href).href;}catch(e){}var skip=false;try{var u=new URL(href,location.href);skip=u.origin!==location.origin||/\\/mtx-(whep|hls)\\//.test(u.pathname);}catch(e){}try{var ch=new Headers(init.headers||{});if((ch.get("Content-Type")||"").toLowerCase().indexOf("application/sdp")>=0)skip=true;}catch(e){}if(!skip&&method!=="GET"&&method!=="HEAD"&&method!=="OPTIONS"){var h=new Headers(init.headers||{});if(!h.has("X-CSRF-TOKEN"))h.set("X-CSRF-TOKEN",t);init.headers=h;}return orig.call(this,input,init);};}})();</script>' . "\n";
}

function security_skip_csrf(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    foreach (['paytr-callback', 'iyzico-callback', 'google-callback'] as $needle) {
        if (str_contains($script, $needle) || str_contains($uri, $needle)) {
            return true;
        }
    }
    return false;
}

function security_host_from_url(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : '';
}

function security_same_site_post(): bool
{
    $expect = security_request_host();
    if ($expect === '') {
        return false;
    }
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        return security_host_from_url($origin) === $expect;
    }
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer !== '') {
        return security_host_from_url($referer) === $expect;
    }
    return false;
}

function security_csrf_valid(): bool
{
    $sent = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $need = (string) ($_SESSION['_csrf'] ?? '');
    return $sent !== '' && $need !== '' && hash_equals($need, $sent);
}

function security_enforce_post(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }
    if (security_skip_csrf()) {
        return;
    }
    if (security_csrf_valid() || security_same_site_post()) {
        return;
    }
    http_response_code(403);
    exit('İstek reddedildi.');
}

function security_throttle_path(): string
{
    $dir = dirname(__DIR__) . '/storage/security';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir . '/login.json';
}

function security_throttle_load(): array
{
    $file = security_throttle_path();
    if (!is_file($file)) {
        return [];
    }
    $raw = (string) file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function security_throttle_save(array $data): void
{
    $ok = [];
    $cut = time() - 3600;
    foreach ($data as $k => $row) {
        if (!is_array($row)) {
            continue;
        }
        $at = (int) ($row['at'] ?? 0);
        if ($at >= $cut) {
            $ok[$k] = $row;
        }
    }
    @file_put_contents(security_throttle_path(), json_encode($ok), LOCK_EX);
}

function security_login_blocked(string $email): ?string
{
    $key = security_client_ip() . '|' . strtolower(trim($email));
    $all = security_throttle_load();
    $row = $all[$key] ?? null;
    if (!is_array($row)) {
        return null;
    }
    $n = (int) ($row['n'] ?? 0);
    $at = (int) ($row['at'] ?? 0);
    if ($n >= 8 && (time() - $at) < 900) {
        $left = (int) ceil((900 - (time() - $at)) / 60);
        return 'Çok fazla deneme. ' . max(1, $left) . ' dk sonra yeniden deneyin.';
    }
    return null;
}

function security_login_fail(string $email): void
{
    $key = security_client_ip() . '|' . strtolower(trim($email));
    $all = security_throttle_load();
    $row = is_array($all[$key] ?? null) ? $all[$key] : ['n' => 0, 'at' => 0];
    if ((time() - (int) $row['at']) > 900) {
        $row['n'] = 0;
    }
    $row['n'] = (int) $row['n'] + 1;
    $row['at'] = time();
    $all[$key] = $row;
    security_throttle_save($all);
}

function security_login_ok(string $email): void
{
    $key = security_client_ip() . '|' . strtolower(trim($email));
    $all = security_throttle_load();
    unset($all[$key]);
    security_throttle_save($all);
}

function security_spam_blocked(string $kind, int $max, int $seconds): bool
{
    $key = $kind . '|' . security_client_ip();
    $all = security_throttle_load();
    $row = is_array($all[$key] ?? null) ? $all[$key] : ['n' => 0, 'at' => 0];
    if ((time() - (int) $row['at']) > $seconds) {
        $row = ['n' => 0, 'at' => time()];
    }
    if ((int) $row['n'] >= $max) {
        return true;
    }
    $row['n'] = (int) $row['n'] + 1;
    $row['at'] = time();
    $all[$key] = $row;
    security_throttle_save($all);
    return false;
}

function security_lead_blocked(): bool
{
    return security_spam_blocked('lead', 5, 600);
}

function security_contact_blocked(): bool
{
    return security_spam_blocked('contact', 5, 600);
}

function security_register_blocked(): bool
{
    return security_spam_blocked('register', 8, 900);
}

function security_honeypot_filled(): bool
{
    return trim((string) ($_POST['website'] ?? '')) !== '';
}

function security_honeypot_field(): string
{
    return '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
}
