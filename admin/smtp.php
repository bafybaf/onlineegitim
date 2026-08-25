<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$saved = false;
$tested = false;
$testOk = false;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action') ?: 'save';
    if ($action === 'save') {
        $pass = post('smtp_pass');
        if ($pass === '') {
            $pass = setting('smtp_pass');
        }
        $port = (string) max(1, min(65535, (int) post('smtp_port') ?: 587));
        $enc = post('smtp_encryption');
        if (!in_array($enc, ['tls', 'ssl', 'none'], true)) {
            $enc = 'tls';
        }
        $pairs = [
            'smtp_enabled' => isset($_POST['smtp_enabled']) ? '1' : '0',
            'smtp_host' => post('smtp_host'),
            'smtp_port' => $port,
            'smtp_user' => post('smtp_user'),
            'smtp_pass' => $pass,
            'smtp_encryption' => $enc,
            'smtp_from_email' => post('smtp_from_email'),
            'smtp_from_name' => post('smtp_from_name') ?: 'Online İlahiyat',
            'smtp_to_email' => post('smtp_to_email') ?: 'info@onlineilahiyat.com',
        ];
        foreach ($pairs as $k => $v) {
            setting_set($k, $v);
        }
        $saved = true;
    } elseif ($action === 'test') {
        $tested = true;
        $to = admin_inbox();
        $html = mail_wrap('SMTP test', '<p>Bu bir test e-postasıdır. SMTP ayarlarınız çalışıyor.</p>');
        $testOk = send_mail($to, 'SMTP test · Online İlahiyat', $html, 'SMTP test e-postası.');
        if (!$testOk) {
            $err = 'Test gönderilemedi. SMTP bilgilerini kontrol edin. Formlar yine de veritabanına kaydedilir.';
        }
    }
}
panel_head('admin', 'smtp', 'SMTP ayarları | Admin', $u);
?>
<?php if ($saved): ?><p class="mb-4 font-bold text-green-700">SMTP ayarları kaydedildi.</p><?php endif; ?>
<?php if ($tested && $testOk): ?><p class="mb-4 font-bold text-green-700">Test e-postası <?= e(admin_inbox()) ?> adresine gönderildi.</p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
  <form method="post" class="card grid gap-4 p-6">
    <input type="hidden" name="action" value="save">
    <p class="text-sm text-muted">İletişim, “Sizi arayalım” ve yeni kayıt bildirimleri PHPMailer ile bu hesaptan gider. SMTP kapalıysa formlar yalnızca veritabanına yazılır.</p>
    <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="smtp_enabled" value="1" <?= setting_bool('smtp_enabled') ? 'checked' : '' ?>> SMTP ile e-posta gönder</label>
    <label class="text-sm font-bold">Sunucu (host)
      <input name="smtp_host" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="smtp.gmail.com" value="<?= e(setting('smtp_host')) ?>" autocomplete="off">
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-bold">Port
        <input name="smtp_port" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('smtp_port', '587')) ?>">
      </label>
      <label class="text-sm font-bold">Şifreleme
        <select name="smtp_encryption" class="mt-1 w-full rounded-xl border px-3 py-2">
          <option value="tls" <?= setting('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
          <option value="ssl" <?= setting('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
          <option value="none" <?= setting('smtp_encryption') === 'none' ? 'selected' : '' ?>>Yok</option>
        </select>
      </label>
    </div>
    <label class="text-sm font-bold">Kullanıcı adı
      <input name="smtp_user" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('smtp_user')) ?>" autocomplete="off">
    </label>
    <label class="text-sm font-bold">Şifre
      <input type="password" name="smtp_pass" class="mt-1 w-full rounded-xl border px-3 py-2" value="" autocomplete="new-password" placeholder="<?= setting('smtp_pass') !== '' ? 'Kayıtlı şifre korunacak (değiştirmek için yazın)' : '' ?>">
    </label>
    <label class="text-sm font-bold">Gönderen e-posta
      <input type="email" name="smtp_from_email" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('smtp_from_email')) ?>">
    </label>
    <label class="text-sm font-bold">Gönderen adı
      <input name="smtp_from_name" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('smtp_from_name', 'Online İlahiyat')) ?>">
    </label>
    <label class="text-sm font-bold">Form gelen kutusu
      <input type="email" name="smtp_to_email" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e(setting('smtp_to_email', 'info@onlineilahiyat.com')) ?>">
      <span class="mt-1 block text-xs font-normal text-muted">İletişim ve arama talepleri bu adrese düşer.</span>
    </label>
    <button class="btn-primary">Kaydet</button>
  </form>
  <aside class="grid gap-4">
    <div class="card p-5">
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Durum</p>
      <p class="mt-2 font-extrabold"><?= smtp_ready() ? 'Gönderime hazır' : (setting_bool('smtp_enabled') ? 'Eksik bilgi veya PHPMailer yok' : 'SMTP kapalı') ?></p>
      <p class="mt-1 text-sm text-muted">Alıcı: <?= e(admin_inbox()) ?></p>
    </div>
    <form method="post" class="card p-5">
      <input type="hidden" name="action" value="test">
      <p class="text-sm text-muted">Kayıtlı gelen kutusuna kısa bir test maili gönderir.</p>
      <button class="btn-primary mt-3">Test gönder</button>
    </form>
  </aside>
</div>
<?php panel_foot();
