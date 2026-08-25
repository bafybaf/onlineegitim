<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$err = '';
$u = current_user();
if ($u && is_admin_role($u['role'])) {
    redirect(panel_home('admin'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = attempt_login(post('email'), post('password'), ['admin']);
    if ($res['ok']) {
        finish_login($res['user']);
    }
    $err = $res['error'];
}
public_head('Yönetim girişi | Online İlahiyat');
?>
<main class="mx-auto max-w-md px-4 py-16">
  <p class="text-center text-xs font-extrabold uppercase tracking-[0.18em] text-muted">Personel</p>
  <h1 class="font-display mt-2 text-center text-4xl">Yönetim girişi</h1>
  <p class="mt-2 text-center text-sm text-muted">Yalnızca yöneticiler. Öğrenci ve öğretmen ders girişini kullanır.</p>
  <?php if ($err): ?><p class="mt-4 text-center font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="card mt-8 grid gap-3 p-6">
    <?= csrf_field() ?>
    <label class="text-sm font-bold">E-posta<input type="email" name="email" required class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="username"></label>
    <label class="text-sm font-bold">Şifre<input type="password" name="password" required class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="current-password"></label>
    <button class="btn-primary">Yönetime gir</button>
  </form>
</main>
<?php public_foot();
