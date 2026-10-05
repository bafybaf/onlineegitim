<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$saved = false;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api = trim(post('sipay_api_key'));
    $secret = trim(post('sipay_api_secret'));
    $mkey = trim(post('sipay_merchant_key'));
    $mid = trim(post('sipay_merchant_id'));
    if ($api === '') {
        $api = setting('sipay_api_key');
    }
    if ($secret === '') {
        $secret = setting('sipay_api_secret');
    }
    if ($api === '' || $secret === '' || $mkey === '' || $mid === '') {
        $err = 'API key, API secret, merchant key ve merchant ID zorunludur.';
    } else {
        foreach ([
            'sipay_api_key' => $api,
            'sipay_api_secret' => $secret,
            'sipay_merchant_key' => $mkey,
            'sipay_merchant_id' => $mid,
            'sipay_test_mode' => isset($_POST['sipay_test_mode']) ? '1' : '0',
            'sipay_max_installment' => (string) max(1, min(12, (int) post('sipay_max_installment') ?: 12)),
            'sipay_sale_webhook_key' => trim(post('sipay_sale_webhook_key')),
            'sipay_public_ip' => trim(post('sipay_public_ip')),
            'site_url' => rtrim(trim(post('site_url')), '/'),
        ] as $k => $v) {
            setting_set($k, $v);
        }
        $saved = true;
    }
}
$notify = sipay_callback_url();
panel_head('admin', 'odeme', 'Sipay ayarları | Admin', $u);
?>
<?php if ($saved): ?><p class="mb-4 font-bold text-green-700">Ayarlar kaydedildi. Sipay mağaza panelinde callback URL’yi güncelleyin.</p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
  <form method="post" class="card grid gap-4 p-6">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Sipay</p>
    <p class="text-sm text-muted">Kart tahsilatı Sipay ödeme linki ile yapılır. Anahtarlar <a class="font-extrabold text-navy" href="https://app.sipay.com.tr/" target="_blank" rel="noreferrer">Sipay üye işyeri</a> panelinden alınır. Kart bilgisi sitede tutulmaz.</p>
    <label class="text-sm font-bold">Site adresi (site_url)
      <input name="site_url" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('site_url')) ?>" placeholder="https://onlineilahiyat.com">
      <span class="mt-1 block text-xs font-normal text-muted">Sipay dönüş ve callback adresleri bu adresten üretilir. Canlıda kök domain yazın.</span>
    </label>
    <label class="text-sm font-bold">API key
      <input type="password" name="sipay_api_key" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('sipay_api_key')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">API secret
      <input type="password" name="sipay_api_secret" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('sipay_api_secret')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Merchant key
      <input name="sipay_merchant_key" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('sipay_merchant_key')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Merchant ID
      <input name="sipay_merchant_id" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('sipay_merchant_id')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">En fazla taksit
      <input type="number" min="1" max="12" name="sipay_max_installment" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('sipay_max_installment', '12')) ?>">
    </label>
    <label class="text-sm font-bold">Sale webhook key <span class="font-normal text-muted">(isteğe bağlı)</span>
      <input name="sipay_sale_webhook_key" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('sipay_sale_webhook_key')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Genel IP (yerel test)
      <input name="sipay_public_ip" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="whatismyip" value="<?= e(setting('sipay_public_ip')) ?>">
      <span class="mt-1 block text-xs font-normal text-muted">XAMPP’te 127.0.0.1 yerine dış IPv4 yazılabilir.</span>
    </label>
    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="sipay_test_mode" value="1" <?= setting_bool('sipay_test_mode', true) ? 'checked' : '' ?>> Test ortamı (provisioning.sipay.com.tr)</label>
    <button class="btn-primary">Sipay ayarlarını kaydet</button>
  </form>
  <aside class="card p-6">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Durum</p>
    <p class="mt-2 font-extrabold"><?= sipay_configured() ? 'Anahtarlar kayıtlı' : 'Henüz yapılandırılmadı' ?></p>
    <p class="mt-1 text-sm text-muted"><?= setting_bool('sipay_test_mode', true) ? 'Test ödemeleri açık' : 'Canlı tahsilat' ?></p>
    <p class="mt-4 text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Callback URL</p>
    <p class="mt-2 break-all text-sm font-extrabold"><?= e($notify) ?></p>
    <p class="mt-3 text-xs text-muted">Sipay Mağaza Paneli → webhook / bildirim URL alanına aynen yapıştırın. Localhost’a bildirim gelmez; canlı domain veya tünel gerekir.</p>
  </aside>
</div>
<?php panel_foot();
