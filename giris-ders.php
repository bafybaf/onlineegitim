<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$err = flash_error();
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$u = current_user();
if ($u && is_edu_role($u['role'])) {
    if ($next !== '') {
        redirect($next);
    }
    if ($u['role'] === 'ogrenci' && membership_needs_pay($u)) {
        redirect(membership_complete_url($u));
    }
    redirect(panel_home($u['role']));
}
if ($u && is_admin_role($u['role'])) {
    redirect(panel_home('admin'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = attempt_login(post('email'), post('password'), ['ogrenci', 'ogretmen']);
    if ($res['ok']) {
        finish_login($res['user'], $next);
    }
    $err = $res['error'];
}
public_head('Ders girişi | Online İlahiyat');
?>
<main class="mx-auto max-w-md px-4 py-16">
  <p class="text-center text-xs font-extrabold uppercase tracking-[0.18em] text-navy">Canlı eğitim</p>
  <h1 class="font-display mt-2 text-center text-4xl">Ders girişi</h1>
  <p class="mt-2 text-center text-sm text-muted">Öğrenci ve öğretmen aynı formdan girer. Kitap ödemesi için mağaza girişi gerekir.</p>
  <?php if ($u && is_shop_role($u['role'])): ?>
    <p class="mt-4 rounded-xl bg-soft px-3 py-2 text-center text-sm font-bold">Açık mağaza oturumunuz var. Canlı ders için eğitim üyeliğiyle giriş yapın.</p>
  <?php endif; ?>
  <?php if ($err): ?><p class="mt-4 text-center font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="card mt-8 grid gap-3 p-6">
    <?= csrf_field() ?>
    <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
    <label class="text-sm font-bold">E-posta<input type="email" name="email" required class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="username"></label>
    <label class="text-sm font-bold">Şifre<input type="password" name="password" required class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="current-password"></label>
    <button class="btn-primary">Derse gir</button>
    <?php google_button('ders', $next !== '' ? $next : '', 'mt-3'); ?>
  </form>
  <div class="mt-6 grid gap-2 text-sm">
    <p class="text-muted">Kitap siparişi için <a class="font-extrabold text-navy" href="<?= e(page_url('giris-magaza')) ?>">mağaza girişini</a> kullanın.</p>
    <p class="text-center"><a class="font-extrabold text-navy" href="<?= e(page_url('kayit-ders')) ?>">Ders kaydı</a></p>
  </div>
</main>
<?php public_foot();
