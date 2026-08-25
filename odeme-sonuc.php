<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$u = current_user();
$oid = (string) ($_GET['oid'] ?? '');
$durum = (string) ($_GET['durum'] ?? 'ok');
$payment = $oid !== '' ? payment_by_oid($oid) : null;
if ($u && $payment && (int) $payment['user_id'] !== (int) $u['id']) {
    $payment = null;
}
if ($payment && $durum !== 'hata' && ($payment['status'] ?? '') !== 'odendi') {
    $payment = payment_settle_now($payment);
}

public_head('Ödeme sonucu | Online İlahiyat');
$okHref = $payment ? payment_success_href($payment) : url('magaza/kitaplarim.php');
$retryHref = payment_retry_href($payment);
$paid = $payment && ($payment['status'] ?? '') === 'odendi';
$okMsg = match ($payment['kind'] ?? '') {
    'program' => 'Program kaydınız açıldı.',
    'uyelik_ders' => 'Canlı ders üyeliğiniz hesabınıza düştü.',
    'uyelik_magaza' => 'Ödeme kaydı işlendi.',
    'kitap' => 'Siparişiniz alındı. Kitaplarım bölümünden takip edin.',
    default => 'Satın alımınız hesabınıza düştü.',
};
?>
<main class="mx-auto max-w-xl px-4 py-14 lg:px-8">
  <div class="card p-8" id="pay-result">
    <?php if ($durum === 'hata'): ?>
      <h1 class="font-display text-3xl">Satın alım tamamlanamadı</h1>
      <p class="mt-3 text-muted" id="pay-msg"><?= e($payment['fail_reason'] ?? 'İşlem iptal edildi.') ?></p>
      <a class="btn-primary mt-6" href="<?= e($retryHref) ?>">Tekrar dene</a>
    <?php else: ?>
      <h1 class="font-display text-3xl">Teşekkürler</h1>
      <p class="mt-3 text-muted" id="pay-msg"><?= e($paid ? $okMsg : 'Satın alımınız işleniyor.') ?></p>
      <p class="mt-2 text-sm font-bold" id="pay-status"><?= $paid ? 'Tamamlandı' : e((string) ($payment['status'] ?? '')) ?></p>
      <a class="btn-primary mt-6<?= $paid ? '' : ' hidden' ?>" id="pay-go" href="<?= e($okHref) ?>">Panele git</a>
    <?php endif; ?>
  </div>
</main>
<?php if ($durum !== 'hata' && $oid !== '' && !$paid): ?>
<script>
(function () {
  const oid = <?= json_encode($oid) ?>;
  const msg = document.getElementById('pay-msg');
  const st = document.getElementById('pay-status');
  const go = document.getElementById('pay-go');
  let n = 0;
  async function tick() {
    n += 1;
    try {
      const r = await fetch(<?= json_encode(url('api/payment-status.php')) ?> + '?oid=' + encodeURIComponent(oid));
      const j = await r.json();
      if (j.status === 'odendi') {
        st.textContent = 'Tamamlandı';
        const labels = { program: 'Program kaydınız açıldı.', uyelik_ders: 'Canlı ders üyeliğiniz hesabınıza düştü.', uyelik_magaza: 'Ödeme kaydı işlendi.', kitap: 'Siparişiniz alındı. Kitaplarım bölümünden takip edin.' };
        msg.textContent = labels[j.kind] || 'Satın alımınız hesabınıza düştü.';
        go.classList.remove('hidden');
        return;
      }
      if (j.status === 'basarisiz') {
        st.textContent = 'Başarısız';
        msg.textContent = j.fail_reason || 'Satın alım onaylanmadı.';
        return;
      }
    } catch (e) {}
    if (n < 20) setTimeout(tick, 1500);
    else msg.textContent = 'Onay biraz gecikti. Panele girip siparişinizi kontrol edebilirsiniz.';
  }
  tick();
})();
</script>
<?php endif; ?>
<?php public_foot();
