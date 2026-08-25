<?php
require_once __DIR__ . '/lib/bootstrap.php';

$sess = $_SESSION['google_oauth'] ?? null;
unset($_SESSION['google_oauth']);
$ctx = is_array($sess) && (($sess['ctx'] ?? '') === 'magaza') ? 'magaza' : 'ders';
$next = is_array($sess) ? safe_next((string) ($sess['next'] ?? '')) : '';
$back = $ctx === 'magaza' ? 'giris-magaza.php' : 'giris-ders.php';

try {
    if (!google_configured()) {
        throw new RuntimeException('Google girişi kapalı.');
    }
    $err = trim((string) ($_GET['error'] ?? ''));
    if ($err !== '') {
        throw new RuntimeException('Google iptal edildi.');
    }
    if (!is_array($sess) || (int) ($sess['exp'] ?? 0) < time()) {
        throw new RuntimeException('Google oturumu zaman aşımına uğradı. Tekrar deneyin.');
    }
    $state = (string) ($_GET['state'] ?? '');
    if ($state === '' || !hash_equals((string) $sess['state'], $state)) {
        throw new RuntimeException('Google doğrulaması geçersiz.');
    }
    $code = trim((string) ($_GET['code'] ?? ''));
    if ($code === '') {
        throw new RuntimeException('Google kodu gelmedi.');
    }
    $tokens = google_exchange_code($code);
    $profile = google_profile_from_tokens($tokens);
    $user = google_login_or_register($ctx, $profile);
    google_finish($user, $ctx, $next);
} catch (Throwable $e) {
    flash_error($e->getMessage());
    redirect($back);
}
