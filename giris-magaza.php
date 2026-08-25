<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$err = flash_error();
$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$u = current_user();
if ($u && is_shop_role($u['role'])) {
    if ($next !== '') {
        redirect($next);
    }
    redirect(panel_home($u['role']));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = attempt_login(post('email'), post('password'), ['musteri']);
    if ($res['ok']) {
        finish_login($res['user'], $next);
    }
    $err = $res['error'];
}
public_head('Mağaza girişi | Online İlahiyat');
?>
<main class="mx-auto max-w-md px-4 py-16">
  <p class="text-center text-xs font-extrabold uppercase tracking-[0.18em] text-accent">Kitap mağazası</p>
  <h1 class="font-display mt-2 text-center text-4xl">Mağaza girişi</h1>
  <p class="mt-2 text-center text-sm text-muted">Yalnızca kitap siparişi ve hesabım için. Canlı ders bu hesapla açılmaz.</p>
  <?php if ($u && !is_shop_role($u['role'])): ?>
    <p class="mt-4 rounded-xl bg-soft px-3 py-2 text-center text-sm font-bold">Açık eğitim oturumunuz var. Mağaza için ayrı üyelikle giriş yapın.</p>
  <?php endif; ?>
  <?php if ($err): ?><p class="mt-4 text-center font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="card mt-8 grid gap-3 p-6">
    <?= csrf_field() ?>
    <?php if ($next !== ''): ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
    <label class="text-sm font-bold">E-posta<input type="email" name="email" required class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="username"></label>
    <label class="text-sm font-bold">Şifre<input type="password" name="password" required class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="current-password"></label>
    <button class="btn-primary">Mağazaya gir</button>
    <?php google_button('magaza', $next !== '' ? $next : 'magaza', 'mt-3'); ?>
  </form>
  <div class="mt-6 grid gap-2 text-sm">
    <p class="text-muted">Canlı ders için <a class="font-extrabold text-navy" href="<?= e(page_url('giris-ders')) ?>">ders girişini</a> kullanın.</p>
    <p class="text-center"><a class="font-extrabold text-navy" href="<?= e(page_url('kayit-magaza')) ?>">Mağaza kaydı</a></p>
  </div>
</main>
<?php public_foot();
