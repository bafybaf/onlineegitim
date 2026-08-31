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

if ((int) ($payment['total'] ?? 0) < 1 || !iyzico_configured()) {
    $payment = payment_settle_now($payment);
    redirect(odeme_sonuc_url('ok', $oid));
}

$init = iyzico_init_checkout($payment, $u);
if (empty($init['ok'])) {
    public_head('Ödeme | Online İlahiyat');
    echo '<main class="mx-auto max-w-xl px-4 py-16"><div class="card p-8"><h1 class="font-display text-3xl">Ödeme açılamadı</h1><p class="mt-3 text-muted">' . e((string) ($init['error'] ?? '')) . '</p><a class="btn-primary mt-6" href="' . e(payment_retry_href($payment)) . '">Tekrar dene</a></div></main>';
    public_foot();
    exit;
}

public_head('Güvenli ödeme | Online İlahiyat');
?>
<main class="mx-auto max-w-3xl px-4 py-10 lg:px-8">
  <div class="card overflow-hidden p-6">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">iyzico</p>
    <h1 class="font-display mt-1 text-3xl">Kart ile öde</h1>
    <p class="mt-2 text-sm text-muted"><?= e(money((int) $payment['total'])) ?> · sipariş <?= e($oid) ?></p>
    <div class="mt-6 min-h-[420px]">
      <?= $init['html'] ?>
    </div>
  </div>
</main>
<?php
public_foot();
