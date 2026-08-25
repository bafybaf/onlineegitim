<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (oi_install_has_admin()) {
    redirect('wiys');
}

$err = '';
$name = trim(post('name') ?: 'Yönetici');
$email = strtolower(trim(post('email') ?: 'admin@onlineilahiyat.com'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password2'] ?? '');
    try {
        if ($password !== $confirm) {
            throw new InvalidArgumentException('Şifreler eşleşmiyor.');
        }
        oi_install_create_admin(db(), $name, $email, $password);
        $st = db()->prepare('SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1');
        $st->execute([$email, 'admin']);
        $user = $st->fetch();
        if (is_array($user)) {
            login_user($user);
        }
        redirect('admin');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

public_head('Kurulum | Online İlahiyat');
?>
<main class="mx-auto max-w-md px-4 py-16">
  <p class="text-center text-xs font-extrabold uppercase tracking-[0.18em] text-navy">İlk kurulum</p>
  <h1 class="font-display mt-2 text-center text-4xl">Yönetici oluştur</h1>
  <p class="mt-2 text-center text-sm text-muted">Veritabanı hazır. Siteyi kullanmak için tek bir yönetici hesabı tanımlayın.</p>
  <?php if ($err): ?><p class="mt-4 text-center font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="card mt-8 grid gap-3 p-6">
    <?= csrf_field() ?>
    <label class="text-sm font-bold">Ad<input type="text" name="name" required value="<?= e($name) ?>" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="name"></label>
    <label class="text-sm font-bold">E-posta<input type="email" name="email" required value="<?= e($email) ?>" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="username"></label>
    <label class="text-sm font-bold">Şifre (en az 10 karakter)<input type="password" name="password" required minlength="10" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="new-password"></label>
    <label class="text-sm font-bold">Şifre tekrar<input type="password" name="password2" required minlength="10" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="new-password"></label>
    <button class="btn-primary">Kurulumu bitir</button>
  </form>
</main>
<?php public_foot();
