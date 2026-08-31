<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!handle_own_password_post($u, $ok, $err)) {
        $name = post('name');
        $phone = post('phone');
        $city = post('city');
        if (mb_strlen($name) < 2) {
            $err = 'Ad soyad en az 2 karakter olmalı.';
        } else {
            update_user_contact((int) $u['id'], $phone, $city, $name);
            $fresh = refresh_current_user((int) $u['id']);
            if ($fresh) {
                $u = $fresh;
            }
            $ok = 'Bilgileriniz kaydedildi.';
        }
    }
}

panel_head('admin', 'hesap', 'Hesabım | Admin', $u);
?>
<section class="card profile-hero p-6">
  <?= user_avatar_html($u, 'lg') ?>
  <div>
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Yönetici hesabı</p>
    <h2 class="font-display mt-1 text-3xl"><?= e((string) $u['name']) ?></h2>
    <p class="mt-1 text-sm text-muted"><?= e((string) $u['email']) ?></p>
  </div>
</section>

<?php if ($ok): ?><p class="mt-5 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mt-5 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<form method="post" class="card mt-6 grid max-w-xl gap-4 p-6">
  <p class="font-extrabold">Profil</p>
  <p class="text-sm text-muted">Ad, telefon ve şehri güncelleyebilirsiniz. E-posta giriş anahtarıdır, değiştirilemez.</p>
  <label class="text-sm font-bold">Ad soyad
    <input required name="name" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $u['name']) ?>">
  </label>
  <label class="text-sm font-bold">Telefon
    <input name="phone" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($u['phone'] ?? '')) ?>">
  </label>
  <label class="text-sm font-bold">Şehir
    <input name="city" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($u['city'] ?? '')) ?>">
  </label>
  <label class="text-sm font-bold">E-posta
    <input readonly class="mt-1 w-full rounded-xl border bg-soft px-3 py-2 text-muted" value="<?= e((string) $u['email']) ?>">
  </label>
  <button class="btn-primary" type="submit">Kaydet</button>
</form>
<?php profile_password_form($u); ?>
<?php panel_foot();
