<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$u = current_user();
$oid = (string) ($_GET['oid'] ?? '');
$payment = $oid !== '' ? payment_by_oid($oid) : null;
if (!$u) {
    $login = payment_login_file($payment);
    $next = $oid !== '' ? 'odeme/' . $oid : 'sepet';
    redirect($login . '?next=' . rawurlencode($next));
}

if (!$payment || (int) $payment['user_id'] !== (int) $u['id']) {
    public_head('Ödeme | Online İlahiyat');
    echo '<main class="mx-auto max-w-xl px-4 py-16"><div class="card p-8"><h1 class="font-display text-3xl">Ödeme bulunamadı</h1><a class="btn-primary mt-6" href="' . e(page_url('sepet')) . '">Sepete dön</a></div></main>';
    public_foot();
    exit;
}

if (!can_pay_kind($u, (string) $payment['kind'])) {
    public_head('Ödeme | Online İlahiyat');
    $msg = membership_is_staff($u)
        ? 'Yönetici ve öğretmen kart ödemez.'
        : 'Bu ödeme bu hesap türüyle alınmaz.';
    echo '<main class="mx-auto max-w-xl px-4 py-16"><div class="card p-8"><h1 class="font-display text-3xl">Ödeme açılamadı</h1><p class="mt-3 text-muted">' . e($msg) . '</p><a class="btn-primary mt-6" href="' . e(url(panel_home($u['role']))) . '">Panele dön</a></div></main>';
    public_foot();
    exit;
}

if (($payment['status'] ?? '') === 'odendi') {
    redirect(odeme_sonuc_url('ok', $oid));
}

if ((int) ($payment['total'] ?? 0) < 1) {
    $payment = payment_settle_now($payment);
    redirect(odeme_sonuc_url('ok', $oid));
}

if (!sipay_configured()) {
    public_head('Ödeme | Online İlahiyat');
    echo '<main class="mx-auto max-w-xl px-4 py-16"><div class="card p-8"><h1 class="font-display text-3xl">Ödeme açılamadı</h1><p class="mt-3 text-muted">Sipay mağaza bilgileri henüz girilmedi. Yönetici panelinden ödeme ayarlarını kaydedin.</p><a class="btn-primary mt-6" href="' . e(payment_retry_href($payment)) . '">Tekrar dene</a></div></main>';
    public_foot();
    exit;
}

$existing = trim((string) ($payment['gateway_token'] ?? ''));
$init = sipay_create_link($payment, $u);
if (!empty($init['ok']) && !empty($init['link'])) {
    redirect((string) $init['link']);
}
if (($payment['provider'] ?? '') === 'sipay' && str_starts_with($existing, 'http')) {
    redirect($existing);
}

public_head('Ödeme | Online İlahiyat');
?>
<main class="mx-auto max-w-xl px-4 py-16">
  <div class="card p-8">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Sipay</p>
    <h1 class="font-display mt-1 text-3xl">Ödeme açılamadı</h1>
    <p class="mt-3 text-muted"><?= e((string) ($init['error'] ?? 'Sipay ödeme sayfası açılamadı.')) ?></p>
    <p class="mt-2 text-sm text-muted"><?= e(money((int) $payment['total'])) ?> · sipariş <?= e($oid) ?></p>
    <a class="btn-primary mt-6" href="<?= e(payment_retry_href($payment)) ?>">Tekrar dene</a>
  </div>
</main>
<?php
public_foot();
