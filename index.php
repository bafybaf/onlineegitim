<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$progs = programs();
$books = db()->query('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id ORDER BY ' . catalog_order_sql('b', 'books') . ' LIMIT 4')->fetchAll();
$homePosts = [];
try {
    $homePosts = db()->query('SELECT slug, title, created_at FROM posts WHERE published=1 ORDER BY ' . catalog_order_sql('', 'posts') . ' LIMIT 3')->fetchAll();
} catch (Throwable) {
}
$homeTitle = setting('seo_home_title');
$homeDesc = setting('seo_home_description');
$heroSlides = home_slides(true);
$heroStrip = home_highlights(true);
$campBanner = campaign_banner();
public_head(
    $homeTitle !== '' ? $homeTitle : 'Online İlahiyat — Canlı Ders, Program ve Kitap',
    $homeDesc !== '' ? $homeDesc : 'Tefsir, hadis, fıkıh, Arapça canlı dersleri ve kitap mağazası.'
);
if (!empty($_SESSION['flash'])) {
    echo '<p class="mx-auto max-w-7xl px-4 pt-4 font-bold text-accent lg:px-8">' . e($_SESSION['flash']) . '</p>';
    unset($_SESSION['flash']);
}
if ($campBanner):
    $campHref = kitaplar_url();
    if (!empty($campBanner['category_id'])) {
        $bannerCat = shop_category_by_id((int) $campBanner['category_id']);
        if ($bannerCat) {
            $campHref = kitaplar_url((string) $bannerCat['slug']);
        }
    }
?>
<section class="bg-navy text-white">
  <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-8">
    <p class="text-sm font-extrabold">
      <?= e((string) $campBanner['title']) ?>
      <?php if (!empty($campBanner['description'])): ?>
        <span class="font-semibold text-white/80"> — <?= e((string) $campBanner['description']) ?></span>
      <?php endif; ?>
      <?php if (!empty($campBanner['code'])): ?>
        <span class="ml-2 rounded-full bg-white/15 px-2 py-0.5 text-xs">Kupon <?= e((string) $campBanner['code']) ?></span>
      <?php else: ?>
        <span class="ml-2 rounded-full bg-white/15 px-2 py-0.5 text-xs"><?= e(campaign_badge_text($campBanner)) ?></span>
      <?php endif; ?>
    </p>
    <a class="text-sm font-extrabold underline" href="<?= e($campHref) ?>">Kitaplara bak</a>
  </div>
</section>
<?php endif; ?>
<?php if ($heroSlides): ?>
<section class="relative overflow-hidden">
  <?php if (count($heroSlides) > 1): ?>
  <button id="hero-prev" class="absolute left-4 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/80 text-navy backdrop-blur md:grid">‹</button>
  <button id="hero-next" class="absolute right-4 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/80 text-navy backdrop-blur md:grid">›</button>
  <?php endif; ?>
  <?php foreach ($heroSlides as $i => $slide):
      $img = home_image_src((string) ($slide['image'] ?? ''));
      $link = home_public_href((string) ($slide['btn1_url'] ?? ''));
  ?>
  <article class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>">
    <?php if ($img !== '' && $link !== ''): ?>
    <a href="<?= e($link) ?>"><img src="<?= e($img) ?>" alt="<?= e((string) ($slide['alt'] ?: $slide['title'])) ?>" class="hero-img block w-full object-cover" /></a>
    <?php elseif ($img !== ''): ?>
    <img src="<?= e($img) ?>" alt="<?= e((string) ($slide['alt'] ?: $slide['title'])) ?>" class="hero-img block w-full object-cover" />
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (count($heroSlides) > 1): ?>
  <div class="dots absolute bottom-3 left-1/2 z-20 flex -translate-x-1/2 gap-2">
    <?php foreach ($heroSlides as $i => $_): ?><button type="button"<?= $i === 0 ? ' class="is-on"' : '' ?>></button><?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php if ($heroStrip): ?>
<section class="border-y border-[#e5e5e7] bg-white">
  <div class="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
    <?php foreach ($heroStrip as $row): ?>
    <p class="flex items-center gap-3 font-extrabold"><span class="grid h-10 w-10 place-items-center rounded-full bg-soft text-navy"><?= e((string) $row['mark']) ?></span> <?= e((string) $row['label']) ?></p>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<section class="py-16">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <div class="flex items-end justify-between gap-4">
      <div><p class="text-xs font-extrabold uppercase tracking-[0.22em] text-accent">Eğitim satış</p><h2 class="font-display mt-2 text-4xl">Seviyenize uygun program</h2></div>
      <a href="<?= e(url('programlar.php')) ?>" class="btn-outline text-sm">Tüm programlar</a>
    </div>
    <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <?php foreach (array_slice($progs, 0, 4) as $p): ?>
      <article class="card overflow-hidden hover:border-navy">
        <?= program_gallery_html($p, 'card', page_url('program', (string) $p['slug'])) ?>
        <a href="<?= e(page_url('program', (string) $p['slug'])) ?>" class="block p-5">
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= e($p['level']) ?></p>
          <h3 class="font-display mt-2 text-2xl"><?= e($p['title']) ?></h3>
          <p class="mt-2 text-sm text-muted"><?= e(mb_strimwidth($p['description'], 0, 80, '…')) ?></p>
          <p class="mt-4"><?= program_price_html($p) ?></p>
        </a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<section class="bg-soft py-16">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <div class="flex items-end justify-between gap-4">
      <div><p class="text-xs font-extrabold uppercase tracking-[0.22em] text-accent">Kitap satışı</p><h2 class="font-display mt-2 text-4xl">Bu ayın kitapları</h2></div>
      <a href="<?= e(url('kitaplar.php')) ?>" class="btn-outline text-sm">Mağazaya git</a>
    </div>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($books as $b): ?>
      <article class="card overflow-hidden">
        <?= book_gallery_html($b, 'card', page_url('kitap', (string) $b['slug'])) ?>
        <div class="p-4">
        <?php $homeCamp = campaign_for_book($b); ?>
        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-navy"><?= e(book_category_name($b)) ?></p>
        <?php if ($homeCamp): ?><p class="mt-1"><span class="badge"><?= e(campaign_badge_text($homeCamp)) ?></span></p><?php endif; ?>
        <h3 class="font-extrabold"><a href="<?= e(page_url('kitap', (string) $b['slug'])) ?>"><?= e($b['title']) ?></a></h3>
        <p class="text-sm text-muted"><?= e($b['author']) ?></p>
          <p class="mt-2"><span class="price-old mr-2"><?= money($b['price_old']) ?></span><span class="price-now"><?= money($b['price']) ?></span></p>
          <button data-add-book="<?= (int) $b['id'] ?>" class="btn-primary mt-3 w-full text-sm">Sepete ekle</button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php if ($homePosts): ?>
<section class="py-16">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <div class="flex items-end justify-between gap-4">
      <div><p class="text-xs font-extrabold uppercase tracking-[0.22em] text-accent">Duyurular</p><h2 class="font-display mt-2 text-4xl">Son yazılar</h2></div>
      <a href="<?= e(page_url('blog')) ?>" class="btn-outline text-sm">Tümü</a>
    </div>
    <div class="mt-8 grid gap-4 md:grid-cols-3">
      <?php foreach ($homePosts as $hp): ?>
        <a class="card p-5 hover:border-navy" href="<?= e(page_url('blog', (string) $hp['slug'])) ?>">
          <p class="text-xs font-extrabold uppercase text-navy"><?= e(date('d.m.Y', strtotime((string) $hp['created_at']))) ?></p>
          <h3 class="font-display mt-2 text-2xl"><?= e($hp['title']) ?></h3>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<section class="bg-navy3 py-16 text-white">
  <div class="mx-auto max-w-7xl px-4 lg:px-8">
    <h2 class="font-display text-4xl">Kayıttan derse 4 adım</h2>
    <div class="mt-8 grid gap-4 md:grid-cols-4">
      <div class="rounded-2xl bg-white/5 p-5"><p class="font-display text-3xl text-accent">1</p><h3 class="mt-2 font-extrabold">Ücretsiz tanışma</h3></div>
      <div class="rounded-2xl bg-white/5 p-5"><p class="font-display text-3xl text-accent">2</p><h3 class="mt-2 font-extrabold">Program + kitap</h3></div>
      <div class="rounded-2xl bg-white/5 p-5"><p class="font-display text-3xl text-accent">3</p><h3 class="mt-2 font-extrabold">Panelden canlı ders</h3><p class="mt-1 text-sm text-white/70">Sınıfa yalnızca panel üzerinden girilir.</p></div>
      <div class="rounded-2xl bg-white/5 p-5"><p class="font-display text-3xl text-accent">4</p><h3 class="mt-2 font-extrabold">Panel takibi</h3></div>
    </div>
  </div>
</section>
<?php public_foot();