<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret = post('google_client_secret');
    if ($secret === '') {
        $secret = setting('google_client_secret');
    }
    $pairs = [
        'google_enabled' => isset($_POST['google_enabled']) ? '1' : '0',
        'google_client_id' => post('google_client_id'),
        'google_client_secret' => $secret,
    ];
    foreach ($pairs as $k => $v) {
        setting_set($k, $v);
    }
    $saved = true;
}
$callback = google_redirect_uri();
$origin = google_origin_hint();
$prod = rtrim(setting('site_url'), '/');
$prodCb = $prod !== '' ? ($prod . '/google-callback') : '';
panel_head('admin', 'google', 'Google girişi | Admin', $u);
?>
<?php if ($saved): ?><p class="mb-4 font-bold text-green-700">Google ayarları kaydedildi.</p><?php endif; ?>
<div class="grid gap-6 lg:grid-cols-[1fr_340px]">
  <form method="post" class="card grid gap-4 p-6">
    <p class="text-sm text-muted">Mağaza ve ders sayfalarındaki “Google ile devam et” butonu yalnızca açık ve anahtarlar doluyken görünür. Yönetici Google ile girmez.</p>
    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="google_enabled" value="1" <?= setting_bool('google_enabled') ? 'checked' : '' ?>> Google ile girişi aç</label>
    <label class="text-sm font-bold">İstemci kimliği (Client ID)
      <input name="google_client_id" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('google_client_id')) ?>" autocomplete="off" placeholder="xxxx.apps.googleusercontent.com">
    </label>
    <label class="text-sm font-bold">İstemci gizli anahtarı (Client secret)
      <input type="password" name="google_client_secret" class="mt-1 w-full rounded-xl border px-3 py-2" value="" autocomplete="new-password" placeholder="<?= setting('google_client_secret') !== '' ? 'Kayıtlı anahtar korunacak (değiştirmek için yazın)' : '' ?>">
    </label>
    <button class="btn-primary">Kaydet</button>
  </form>
  <aside class="grid gap-4">
    <div class="card p-5">
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Durum</p>
      <p class="mt-2 font-extrabold"><?= google_configured() ? 'Butonlar görünür' : 'Kapalı / eksik — butonlar gizli' ?></p>
    </div>
    <div class="card p-5 text-sm">
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Google Cloud Console</p>
      <p class="mt-2 text-muted">OAuth 2.0 istemci türü: <b>Web uygulaması</b>.</p>
      <p class="mt-3 font-extrabold">Yetkili JavaScript kaynağı</p>
      <p class="mt-1 break-all font-bold"><?= e($origin) ?></p>
      <p class="mt-1 text-xs text-muted">Yerelde genelde <b>http://localhost</b></p>
      <p class="mt-3 font-extrabold">Yetkili yönlendirme URI</p>
      <p class="mt-1 break-all font-bold"><?= e($callback) ?></p>
      <?php if ($prodCb !== '' && $prodCb !== $callback): ?>
        <p class="mt-2 break-all text-xs text-muted">Canlı (site_url): <?= e($prodCb) ?></p>
      <?php endif; ?>
      <p class="mt-3 text-xs text-muted">Ödeme ayarlarındaki site_url canlı adresi belirler. Yerel: <b>http://localhost/online-ilahiyat/google-callback</b></p>
    </div>
  </aside>
</div>
<?php panel_foot();
