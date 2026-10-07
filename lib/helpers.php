<?php
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if (function_exists('pretty_path')) {
        $path = pretty_path($path);
    }
    return rtrim(BASE_URL, '/') . '/' . $path;
}

function e(?string $s): string
{
    $s = (string) $s;
    if (function_exists('utf8_salvage')) {
        $s = utf8_salvage($s);
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header('Location: ' . $path);
        exit;
    }
    $base = rtrim(BASE_URL, '/');
    if ($base !== '' && (str_starts_with($path, $base . '/') || $path === $base || $path === $base . '/')) {
        header('Location: ' . $path);
        exit;
    }
    header('Location: ' . url($path));
    exit;
}

function money(int|float $n): string
{
    return number_format((int) $n, 0, ',', '.') . '₺';
}

function money_or_free(int|float $n): string
{
    return (int) $n <= 0 ? 'Ücretsiz' : money($n);
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function post(string $key, $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function php_ini_bytes(string $key): int
{
    $raw = trim((string) ini_get($key));
    if ($raw === '' || $raw === '0' || $raw === '-1') {
        return 0;
    }
    if (function_exists('ini_parse_quantity')) {
        $n = (int) ini_parse_quantity($raw);
        return $n > 0 ? $n : 0;
    }
    $unit = strtoupper(substr($raw, -1));
    $n = (float) $raw;
    $mul = ['K' => 1024, 'M' => 1048576, 'G' => 1073741824];
    if (isset($mul[$unit])) {
        $n *= $mul[$unit];
    }
    return (int) $n;
}

function request_post_too_large(): bool
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        return false;
    }
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $max = php_ini_bytes('post_max_size');
    if ($max > 0 && $len > $max) {
        return true;
    }
    foreach ($_FILES as $file) {
        if (!is_array($file)) {
            continue;
        }
        $err = $file['error'] ?? null;
        if (is_array($err)) {
            foreach ($err as $code) {
                if ((int) $code === UPLOAD_ERR_INI_SIZE || (int) $code === UPLOAD_ERR_FORM_SIZE) {
                    return true;
                }
            }
            continue;
        }
        $code = (int) $err;
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return true;
        }
    }
    return $len > 1024 && empty($_POST) && empty($_FILES);
}

function request_upload_limit_message(int $maxMb = 200): string
{
    return 'Dosya sunucuya sığmadı. En fazla ' . $maxMb . ' MB MP4 yükleyin; başlık ve grup bilgisi büyük dosyada kaybolmaz.';
}

function flash_error(?string $msg = null): string
{
    if ($msg !== null) {
        $_SESSION['flash_err'] = $msg;
        return '';
    }
    $e = (string) ($_SESSION['flash_err'] ?? '');
    unset($_SESSION['flash_err']);
    return $e;
}

function flash_ok(?string $msg = null): string
{
    if ($msg !== null) {
        $_SESSION['flash_ok'] = $msg;
        return '';
    }
    $m = (string) ($_SESSION['flash_ok'] ?? '');
    unset($_SESSION['flash_ok']);
    return $m;
}

function cart(): array
{
    if (function_exists('shop_books_visible') && !shop_books_visible()) {
        return [];
    }
    return $_SESSION['cart'] ?? [];
}

function cart_programs(): array
{
    $rows = $_SESSION['cart_programs'] ?? [];
    return is_array($rows) ? $rows : [];
}

function cart_count(): int
{
    $n = 0;
    foreach (cart() as $qty) {
        $n += (int) $qty;
    }
    foreach (cart_programs() as $qty) {
        $n += max(1, (int) $qty);
    }
    return $n;
}

function cart_set(int $bookId, int $qty): void
{
    if (function_exists('shop_books_visible') && !shop_books_visible()) {
        $_SESSION['cart'] = [];
        return;
    }
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if ($qty < 1) {
        unset($_SESSION['cart'][$bookId]);
        return;
    }
    $_SESSION['cart'][$bookId] = min(9, $qty);
}

function cart_program_set(int $programId, int $qty): void
{
    if ($programId < 1) {
        return;
    }
    if (!isset($_SESSION['cart_programs']) || !is_array($_SESSION['cart_programs'])) {
        $_SESSION['cart_programs'] = [];
    }
    if ($qty < 1) {
        unset($_SESSION['cart_programs'][$programId]);
        return;
    }
    $_SESSION['cart_programs'][$programId] = 1;
}

function live_mins(string $startedAt): int
{
    $t = strtotime($startedAt);
    return max(1, (int) round((time() - $t) / 60));
}

function db_try_exec(string $sql, array $args = []): void
{
    try {
        db()->prepare($sql)->execute($args);
    } catch (Throwable) {
    }
}

function panel_delete_form(string $url, array $fields, string $confirm = 'Silinsin mi?', string $label = 'Sil', string $btnClass = 'text-sm font-extrabold text-accent'): string
{
    $html = '<form method="post" action="' . e($url) . '" class="inline" onsubmit=\'return confirm(' . json_encode($confirm, JSON_UNESCAPED_UNICODE) . ')\'>';
    if (function_exists('csrf_field')) {
        $html .= csrf_field();
    }
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">';
    }
    $html .= '<button class="' . e($btnClass) . '">' . e($label) . '</button></form>';
    return $html;
}
