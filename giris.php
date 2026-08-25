<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$u = current_user();
public_head('Giriş | Online İlahiyat');
?>
<main class="mx-auto max-w-3xl px-4 py-16">
  <h1 class="font-display text-center text-4xl">Nereye giriş yapmak istiyorsunuz?</h1>
  <p class="mt-2 text-center text-sm text-muted">Mağaza ve canlı ders hesapları ayrıdır. Tek şifre ile ikisine birden girilmez.</p>
  <?php if ($u): ?>
    <p class="mt-6 text-center text-sm">Şu an <b><?= e($u['name']) ?></b> olarak açıksınız.
      <a class="font-extrabold text-navy" href="<?= e(url(panel_home($u['role']))) ?>"><?= is_shop_role($u['role']) ? 'Hesabıma git' : 'Panele git' ?></a>
      · <a class="font-extrabold text-navy" href="<?= e(page_url('cikis')) ?>">Çıkış</a>
    </p>
  <?php endif; ?>
  <div class="mt-10 grid gap-5 md:grid-cols-2">
    <a href="<?= e(page_url('giris-magaza')) ?>" class="card p-6 hover:border-navy">
      <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent">Kitap mağazası</p>
      <h2 class="font-display mt-2 text-2xl">Mağaza girişi</h2>
      <p class="mt-2 text-sm text-muted">Sipariş, güvenli kart ödemesi ve Kitaplarım. Canlı derse açılmaz.</p>
    </a>
    <a href="<?= e(page_url('giris-ders')) ?>" class="card p-6 hover:border-navy">
      <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-navy">Canlı eğitim</p>
      <h2 class="font-display mt-2 text-2xl">Ders girişi</h2>
      <p class="mt-2 text-sm text-muted">Öğrenci ve öğretmen paneli, canlı sınıf, test ve ödev.</p>
    </a>
  </div>
  <p class="mt-8 text-center text-sm text-muted">Hesabınız yok mu?
    <a class="font-extrabold text-navy" href="<?= e(page_url('kayit-magaza')) ?>">Mağaza kaydı</a>
    ·
    <a class="font-extrabold text-navy" href="<?= e(page_url('kayit-ders')) ?>">Ders kaydı</a>
  </p>
</main>
<?php public_foot();
