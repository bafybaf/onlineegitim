<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$ilgi = trim((string) ($_GET['ilgi'] ?? ''));
$dersQs = $ilgi !== '' ? ('?ilgi=' . rawurlencode($ilgi)) : '';
public_head('Kayıt Ol | Online İlahiyat');
?>
<main class="mx-auto max-w-3xl px-4 py-16">
  <h1 class="font-display text-center text-4xl">Hangi hesabı açmak istiyorsunuz?</h1>
  <p class="mt-2 text-center text-sm text-muted"><?= shop_books_visible() ? 'Mağaza ve canlı ders kayıtları ayrı hesaplardır.' : 'Canlı ders kaydı öğrenci paneline açılır.' ?></p>
  <div class="mt-10 grid gap-5 <?= shop_books_visible() ? 'md:grid-cols-2' : '' ?>">
    <?php if (shop_books_visible()): ?>
    <a href="<?= e(page_url('kayit-magaza')) ?>" class="card p-6 hover:border-navy">
      <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent">Kitap mağazası</p>
      <h2 class="font-display mt-2 text-2xl">Mağaza kaydı</h2>
      <p class="mt-2 text-sm text-muted">Ücretsiz hesap. Kitap siparişi ve Kitaplarım.</p>
    </a>
    <?php endif; ?>
    <a href="<?= e(page_url('kayit-ders') . $dersQs) ?>" class="card p-6 hover:border-navy">
      <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-navy">Canlı eğitim</p>
      <h2 class="font-display mt-2 text-2xl">Ders kaydı</h2>
      <p class="mt-2 text-sm text-muted">Öğrenci paneli ve canlı sınıf.</p>
    </a>
  </div>
  <p class="mt-8 text-center text-sm text-muted">Hesabınız var mı?
    <?php if (shop_books_visible()): ?>
    <a class="font-extrabold text-navy" href="<?= e(page_url('giris-magaza')) ?>">Mağaza girişi</a>
    ·
    <?php endif; ?>
    <a class="font-extrabold text-navy" href="<?= e(page_url('giris-ders')) ?>">Ders girişi</a>
  </p>
</main>
<?php public_foot();
