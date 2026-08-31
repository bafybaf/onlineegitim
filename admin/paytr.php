<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$saved = false;
$err = '';
$iyzicoSaved = false;
$iyzicoErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form_kind') === 'iyzico') {
    $api = trim(post('iyzico_api_key'));
    $secret = trim(post('iyzico_secret_key'));
    if ($api === '' || $secret === '') {
        $iyzicoErr = 'iyzico API key ve secret key zorunludur.';
    } else {
        setting_set('iyzico_api_key', $api);
        setting_set('iyzico_secret_key', $secret);
        setting_set('iyzico_sandbox', isset($_POST['iyzico_sandbox']) ? '1' : '0');
        setting_set('iyzico_enabled', isset($_POST['iyzico_enabled']) ? '1' : '0');
        $iyzicoSaved = true;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = preg_replace('/\D+/', '', post('paytr_merchant_id'));
    $key = trim(post('paytr_merchant_key'));
    $salt = trim(post('paytr_merchant_salt'));
    if ($id === '' || $key === '' || $salt === '') {
        $err = 'Mağaza no, merchant key ve merchant salt zorunludur.';
    } else {
        $pairs = [
            'paytr_merchant_id' => $id,
            'paytr_merchant_key' => $key,
            'paytr_merchant_salt' => $salt,
            'paytr_test_mode' => isset($_POST['paytr_test_mode']) ? '1' : '0',
            'paytr_debug' => isset($_POST['paytr_debug']) ? '1' : '0',
            'paytr_no_installment' => isset($_POST['paytr_no_installment']) ? '1' : '0',
            'paytr_max_installment' => (string) max(0, min(12, (int) post('paytr_max_installment'))),
            'paytr_iframe_v2' => isset($_POST['paytr_iframe_v2']) ? '1' : '0',
            'paytr_ssl_verify' => isset($_POST['paytr_ssl_verify']) ? '1' : '0',
            'paytr_public_ip' => trim(post('paytr_public_ip')),
            'site_url' => rtrim(trim(post('site_url')), '/'),
        ];
        foreach ($pairs as $k => $v) {
            setting_set($k, $v);
        }
        $saved = true;
    }
}
$notify = app_public_url('api/paytr-callback.php');
panel_head('admin', 'paytr', 'PayTR ayarları | Admin', $u);
?>
<?php if ($saved): ?><p class="mb-4 font-bold text-green-700">Ayarlar kaydedildi. PayTR mağaza panelinde Bildirim URL’sini güncelleyin.</p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
  <form method="post" class="card grid gap-4 p-6">
    <input type="hidden" name="form_kind" value="paytr">
    <p class="text-sm text-muted">Mağaza Paneli → Destek &amp; Kurulum → Entegrasyon Bilgileri. Kitap siparişi ile mağaza ve ders üyelikleri bu hesap üzerinden alınır.</p>
    <label class="text-sm font-bold">Mağaza no (merchant_id)
      <input name="paytr_merchant_id" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('paytr_merchant_id')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Merchant key
      <input type="password" name="paytr_merchant_key" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('paytr_merchant_key')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Merchant salt
      <input type="password" name="paytr_merchant_salt" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('paytr_merchant_salt')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Canlı site adresi
      <input name="site_url" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="https://www.siteniz.com/online-ilahiyat" value="<?= e(setting('site_url')) ?>">
      <span class="mt-1 block text-xs font-normal text-muted">PayTR ok/fail yönlendirmesi ve bildirim URL’si için. Yerelde ngrok adresi yazın.</span>
    </label>
    <label class="text-sm font-bold">Dış IP (localhost testi)
      <input name="paytr_public_ip" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="whatismyip" value="<?= e(setting('paytr_public_ip')) ?>">
      <span class="mt-1 block text-xs font-normal text-muted">XAMPP’te 127.0.0.1 gönderilirse PayTR token reddeder. Dış IPv4’ünüzü yazın.</span>
    </label>
    <label class="text-sm font-bold">En fazla taksit
      <input type="number" min="0" max="12" name="paytr_max_installment" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('paytr_max_installment', '0')) ?>">
      <span class="mt-1 block text-xs font-normal text-muted">0 = PayTR’nin izin verdiği en yüksek taksit.</span>
    </label>
    <div class="grid gap-2 text-sm font-bold">
      <label class="flex items-center gap-2"><input type="checkbox" name="paytr_test_mode" value="1" <?= setting_bool('paytr_test_mode', true) ? 'checked' : '' ?>> Test modu</label>
      <label class="flex items-center gap-2"><input type="checkbox" name="paytr_debug" value="1" <?= setting_bool('paytr_debug', true) ? 'checked' : '' ?>> Hata mesajlarını göster (debug_on)</label>
      <label class="flex items-center gap-2"><input type="checkbox" name="paytr_no_installment" value="1" <?= setting_bool('paytr_no_installment') ? 'checked' : '' ?>> Taksit gösterme</label>
      <label class="flex items-center gap-2"><input type="checkbox" name="paytr_iframe_v2" value="1" <?= setting_bool('paytr_iframe_v2', true) ? 'checked' : '' ?>> Yeni iframe tasarımı (v2)</label>
      <label class="flex items-center gap-2"><input type="checkbox" name="paytr_ssl_verify" value="1" <?= setting_bool('paytr_ssl_verify', true) ? 'checked' : '' ?>> SSL sertifika doğrula (canlıda açık kalsın)</label>
    </div>
    <button class="btn-primary">Kaydet</button>
  </form>
  <aside class="grid gap-4">
    <div class="card p-5">
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Durum</p>
      <p class="mt-2 font-extrabold"><?= paytr_configured() ? 'Anahtarlar kayıtlı' : 'Henüz yapılandırılmadı' ?></p>
      <p class="mt-1 text-sm text-muted"><?= setting_bool('paytr_test_mode', true) ? 'Test ödemeleri açık' : 'Canlı tahsilat' ?></p>
    </div>
    <div class="card p-5">
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Bildirim URL</p>
      <p class="mt-2 break-all text-sm font-bold"><?= e($notify) ?></p>
      <p class="mt-3 text-xs text-muted">PayTR Mağaza Paneli → Bildirim URL alanına aynen yapıştırın. Localhost’a bildirim gelmez; canlı domain veya tünel gerekir.</p>
    </div>
  </aside>
</div>
<?php if ($iyzicoSaved): ?><p class="mt-6 font-bold text-green-700">iyzico ayarları kaydedildi.</p><?php endif; ?>
<?php if ($iyzicoErr): ?><p class="mt-6 font-bold text-accent"><?= e($iyzicoErr) ?></p><?php endif; ?>
<form method="post" class="card mt-6 grid gap-4 p-6">
  <input type="hidden" name="form_kind" value="iyzico">
  <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">iyzico</p>
  <p class="text-sm text-muted">PayTR’ye ek kart tahsilatı. Anahtarlar <a class="font-extrabold text-navy" href="https://merchant.iyzipay.com/" target="_blank" rel="noreferrer">iyzico üye işyeri</a> panelinden alınır. Açıkken sepet ve ders üyeliği iyzico ödeme formuna gider.</p>
  <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="iyzico_enabled" value="1" <?= setting_bool('iyzico_enabled') ? 'checked' : '' ?>> iyzico ödemesini kullan</label>
  <label class="text-sm font-bold">API key
    <input type="password" name="iyzico_api_key" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('iyzico_api_key')) ?>" autocomplete="off">
  </label>
  <label class="text-sm font-bold">Secret key
    <input type="password" name="iyzico_secret_key" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('iyzico_secret_key')) ?>" autocomplete="off">
  </label>
  <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="iyzico_sandbox" value="1" <?= setting_bool('iyzico_sandbox', true) ? 'checked' : '' ?>> Sandbox (test) ortamı</label>
  <p class="text-xs text-muted">Callback: <b><?= e(app_public_url('api/iyzico-callback.php')) ?></b> · Canlı sitede <b>site_url</b> doğru olsun. Test kartları: <a class="text-navy" href="https://github.com/iyzico/iyzipay-php" target="_blank" rel="noreferrer">iyzipay-php</a></p>
  <button class="btn-primary">iyzico’yu kaydet</button>
</form>
<?php panel_foot();
