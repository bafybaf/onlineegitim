<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$err = flash_error();
$u = current_user();
if ($u && is_shop_role($u['role'])) {
    redirect(panel_home($u['role']));
}
if ($u && !is_shop_role($u['role'])) {
    $err = $err ?: 'Mağaza hesabı ayrıdır. Mağaza için farklı e-posta kullanın veya çıkış yapın.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($u && !is_shop_role($u['role'])) {
        $err = 'Açık eğitim oturumunuz var. Mağaza için ayrı e-posta ile kaydolun.';
    } else {
        try {
            $name = post('name');
            $email = post('email');
            $phone = post('phone');
            $pass = post('password');
            if (security_honeypot_filled()) {
                throw new RuntimeException('Kayıt alınamadı. Yeniden deneyin.');
            }
            if (security_register_blocked()) {
                throw new RuntimeException('Çok fazla kayıt denemesi. Bir süre sonra yeniden deneyin.');
            }
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Ad ve geçerli e-posta girin.');
            }
            if (strlen($pass) < 8) {
                throw new RuntimeException('Şifre en az 8 karakter olmalı.');
            }
            if ($phone === '') {
                throw new RuntimeException('Telefon girin.');
            }
            $st = db()->prepare('SELECT id, role FROM users WHERE email = ?');
            $st->execute([$email]);
            $exists = $st->fetch();
            if ($exists) {
                throw new RuntimeException($exists['role'] === 'musteri'
                    ? 'Bu e-posta zaten mağaza hesabı. Mağaza girişini kullanın.'
                    : 'Bu e-posta eğitim hesabı. Mağaza için ayrı e-posta kullanın.');
            }
            $nu = register_shop_user($name, $email, $pass, $phone);
            login_user($nu);
            redirect(panel_home('musteri'));
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}
$shopForm = !$u || is_shop_role($u['role']);
public_head('Mağaza kaydı | Online İlahiyat');
?>
<main class="mx-auto grid max-w-6xl gap-10 px-4 py-14 lg:grid-cols-2 lg:px-8">
  <div>
    <p class="badge">Kitap mağazası</p>
    <h1 class="font-display mt-4 text-4xl md:text-5xl">Mağaza hesabı</h1>
    <p class="mt-3 text-muted">Hesap ücretsizdir. Kitapları sepetten kart ile ödersiniz. Canlı ders bu hesapla girilemez.</p>
    <img src="<?= e(url('assets/img/hero-kitap.jpg')) ?>" alt="" class="mt-8 h-72 w-full rounded-[22px] object-cover" />
  </div>
  <?php if (!$shopForm): ?>
    <div class="card p-6">
      <?php if ($err): ?><p class="mb-3 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
      <a class="btn-primary" href="<?= e(url('cikis.php')) ?>">Çıkış yapıp mağaza kaydı</a>
    </div>
  <?php else: ?>
  <form method="post" class="card p-6">
    <?= csrf_field() ?>
    <?= security_honeypot_field() ?>
    <?php if ($err): ?><p class="mb-3 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
    <label class="text-sm font-bold">Ad soyad<input required name="name" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="name"></label>
    <label class="mt-3 block text-sm font-bold">Telefon<input required name="phone" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="tel"></label>
    <label class="mt-3 block text-sm font-bold">E-posta<input type="email" required name="email" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="email"></label>
    <label class="mt-3 block text-sm font-bold">Şifre<input type="password" required minlength="8" name="password" class="mt-1 w-full rounded-xl border px-3 py-2" placeholder="En az 8 karakter" autocomplete="new-password"></label>
    <button class="btn-primary mt-5 w-full">Hesabı aç</button>
    <?php google_button('magaza', 'magaza', 'mt-4'); ?>
    <p class="mt-3 text-center text-sm text-muted">Hesabınız var mı? <a class="font-extrabold text-navy" href="<?= e(page_url('giris-magaza')) ?>">Mağaza girişi</a></p>
  </form>
  <?php endif; ?>
</main>
<?php public_foot();
