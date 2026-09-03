<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$catSlug = trim((string) ($_GET['kategori'] ?? ''));
$cats = shop_categories();
$activeCat = $catSlug !== '' ? shop_category_by_slug($catSlug) : null;
$books = books_with_category($activeCat ? (string) $activeCat['slug'] : null);
public_head('Kitap Mağazası | Online İlahiyat');
?>
<header class="bg-soft py-14">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <h1 class="font-display text-4xl md:text-6xl"><?= $activeCat ? e((string) $activeCat['name']) : 'Kitap mağazası' ?></h1>
    <?php if ($activeCat): ?>
      <p class="mt-2 text-muted"><a class="font-extrabold text-navy" href="<?= e(kitaplar_url()) ?>">Tüm kitaplar</a></p>
    <?php endif; ?>
  </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-12 lg:px-8">
  <nav class="mb-8 flex flex-wrap gap-2">
    <a class="rounded-full border px-4 py-2 text-sm font-extrabold <?= !$activeCat ? 'border-navy bg-navy text-white' : 'border-[#e5e5e7] hover:border-navy' ?>" href="<?= e(kitaplar_url()) ?>">Tümü</a>
    <a class="rounded-full border px-4 py-2 text-sm font-extrabold <?= $activeCat && ($activeCat['slug'] ?? '') === 'dkab-ihl' ? 'border-navy bg-navy text-white' : 'border-[#e5e5e7] hover:border-navy' ?>" href="<?= e(kitaplar_url('dkab-ihl')) ?>">DKAB-İHL</a>
    <a class="rounded-full border px-4 py-2 text-sm font-extrabold <?= $activeCat && ($activeCat['slug'] ?? '') === 'mbsts' ? 'border-navy bg-navy text-white' : 'border-[#e5e5e7] hover:border-navy' ?>" href="<?= e(kitaplar_url('mbsts')) ?>">MBSTS</a>
    <a class="rounded-full border px-4 py-2 text-sm font-extrabold <?= $activeCat && ($activeCat['slug'] ?? '') === 'dhbt' ? 'border-navy bg-navy text-white' : 'border-[#e5e5e7] hover:border-navy' ?>" href="<?= e(kitaplar_url('dhbt')) ?>">DHBT</a>
  </nav>
  <?php if (!$books): ?>
    <div class="card p-8 max-w-xl">
      <h2 class="font-display text-3xl">Bu kategoride kitap yok</h2>
      <a href="<?= e(kitaplar_url()) ?>" class="btn-primary mt-6">Tüm kitaplar</a>
    </div>
  <?php else: ?>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($books as $b):
        $camp = campaign_for_book($b); ?>
    <article class="card overflow-hidden">
      <?= book_gallery_html($b, 'card', page_url('kitap', (string) $b['slug'])) ?>
      <div class="p-4">
        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-navy"><?= e(book_category_name($b)) ?></p>
        <?php if ($camp): ?>
          <p class="mt-1"><span class="badge"><?= e(campaign_badge_text($camp)) ?></span></p>
        <?php endif; ?>
        <h2 class="mt-1 font-extrabold"><a href="<?= e(page_url('kitap', (string) $b['slug'])) ?>"><?= e($b['title']) ?></a></h2>
        <p class="text-sm text-muted"><?= e($b['author']) ?></p>
        <p class="mt-2"><span class="price-old mr-2"><?= money($b['price_old']) ?></span><span class="price-now"><?= money($b['price']) ?></span></p>
        <button data-add-book="<?= (int) $b['id'] ?>" class="btn-primary mt-3 w-full text-sm">Sepete ekle</button>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<?php public_foot();
