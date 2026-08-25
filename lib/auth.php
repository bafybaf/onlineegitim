<?php
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ? AND status IN ("aktif","bekliyor")');
    $st->execute([$_SESSION['user_id']]);
    $user = $st->fetch() ?: null;
    return $user;
}

function login_user(array $user): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $p = session_get_cookie_params();
        $opts = [
            'expires' => time() - 42000,
            'path' => $p['path'] ?: '/',
            'secure' => (bool) $p['secure'],
            'httponly' => (bool) $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ];
        if (!empty($p['domain'])) {
            $opts['domain'] = $p['domain'];
        }
        setcookie(session_name(), '', $opts);
        session_destroy();
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (function_exists('security_csrf_token')) {
        security_csrf_token();
    }
}

function is_shop_role(string $role): bool
{
    return $role === 'musteri';
}

function is_edu_role(string $role): bool
{
    return in_array($role, ['ogrenci', 'ogretmen'], true);
}

function is_admin_role(string $role): bool
{
    return $role === 'admin';
}

function login_page_for_roles(string ...$roles): string
{
    $onlyShop = $roles === ['musteri'];
    $onlyAdmin = $roles === ['admin'];
    if ($onlyAdmin) {
        return 'giris-admin.php';
    }
    if ($onlyShop) {
        return 'giris-magaza.php';
    }
    return 'giris-ders.php';
}

function require_role(string ...$roles): array
{
    $u = current_user();
    if ($u && in_array($u['role'], $roles, true)) {
        return $u;
    }
    if ($u) {
        redirect(panel_home($u['role']));
    }
    redirect(login_page_for_roles(...$roles));
}

function panel_home(string $role): string
{
    return match ($role) {
        'ogretmen' => 'ogretmen/index.php',
        'admin' => 'admin/index.php',
        'musteri' => 'magaza/index.php',
        default => 'ogrenci/index.php',
    };
}

function safe_next(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '' || str_contains($raw, '://') || str_starts_with($raw, '//') || str_contains($raw, "\n")) {
        return '';
    }
    $raw = ltrim($raw, '/');
    if (str_starts_with($raw, 'api/') || str_starts_with($raw, 'lib/') || str_starts_with($raw, 'config/')) {
        return '';
    }
    return $raw;
}

function attempt_login(string $email, string $password, array $allowedRoles): array
{
    $email = strtolower(trim($email));
    $blocked = security_login_blocked($email);
    if ($blocked) {
        return ['ok' => false, 'error' => $blocked, 'user' => null];
    }
    $st = db()->prepare('SELECT * FROM users WHERE email = ? AND status IN ("aktif","bekliyor")');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password'])) {
        security_login_fail($email);
        return ['ok' => false, 'error' => 'E-posta veya şifre hatalı.', 'user' => null];
    }
    if (!in_array($u['role'], $allowedRoles, true)) {
        $hint = match ($u['role']) {
            'musteri' => 'Bu hesap mağaza üyesidir. Mağaza girişini kullanın.',
            'admin' => 'Bu hesap yönetici girişiyledir.',
            default => 'Bu hesap canlı ders / eğitim üyesidir. Ders girişini kullanın.',
        };
        return ['ok' => false, 'error' => $hint, 'user' => $u];
    }
    security_login_ok($email);
    return ['ok' => true, 'error' => '', 'user' => $u];
}

function finish_login(array $user, string $next = ''): void
{
    login_user($user);
    if ($next !== '') {
        redirect($next);
    }
    if (function_exists('membership_needs_pay') && membership_needs_pay($user) && ($user['status'] ?? '') === 'bekliyor') {
        redirect(membership_complete_url($user));
    }
    redirect(panel_home($user['role']));
}
