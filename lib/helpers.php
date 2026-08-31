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
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    $n = 0;
    foreach (cart() as $qty) {
        $n += (int) $qty;
    }
    return $n;
}

function cart_set(int $bookId, int $qty): void
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if ($qty < 1) {
        unset($_SESSION['cart'][$bookId]);
        return;
    }
    $_SESSION['cart'][$bookId] = min(9, $qty);
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
