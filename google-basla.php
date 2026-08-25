<?php
require_once __DIR__ . '/lib/bootstrap.php';

$ctx = (string) ($_GET['ctx'] ?? 'ders');
$ctx = $ctx === 'magaza' ? 'magaza' : 'ders';
$next = safe_next($_GET['next'] ?? '');
$back = $ctx === 'magaza' ? 'giris-magaza.php' : 'giris-ders.php';

if (!google_configured()) {
    flash_error('Google girişi henüz açılmadı.');
    redirect($back);
}

$u = current_user();
if ($u) {
    if ($ctx === 'magaza' && is_shop_role($u['role'])) {
        redirect($next !== '' ? $next : panel_home($u['role']));
    }
    if ($ctx === 'ders' && is_edu_role($u['role'])) {
        if ($u['role'] === 'ogrenci' && membership_needs_pay($u)) {
            redirect(page_url('uyelik-ders'));
        }
        redirect($next !== '' ? $next : panel_home($u['role']));
    }
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth'] = [
    'state' => $state,
    'ctx' => $ctx,
    'next' => $next,
    'exp' => time() + 600,
];

$params = [
    'client_id' => setting('google_client_id'),
    'redirect_uri' => google_redirect_uri(),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account',
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
