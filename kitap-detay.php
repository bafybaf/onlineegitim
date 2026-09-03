<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
$slug = $_GET['slug'] ?? 'tefsir-ozet';
$st = db()->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE b.slug = ?');
$st->execute([$slug]);
$b = $st->fetch();
if (!$b) {
    redirect('kitaplar.php');
}
$body = catalog_body('book', (string) $b['slug'], (string) ($b['description'] ?? ''));
$paras = catalog_paragraphs($body);
$pages = catalog_book_pages((string) $b['slug'], (int) ($b['pages'] ?? 0));
$publisher = catalog_book_publisher((string) $b['slug'], (string) $b['author'], (string) ($b['publisher'] ?? ''));
$digital = (int) $b['is_digital'] === 1;
$camp = campaign_for_book($b);
$catId = (int) ($b['category_id'] ?? 0);
if ($catId > 0) {
    $relSt = db()->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE b.category_id = ? AND b.id <> ? ORDER BY b.id LIMIT 3');
    $relSt->execute([$catId, (int) $b['id']]);
} else {
    $relSt = db()->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE b.category = ? AND b.id <> ? ORDER BY b.id LIMIT 3');
    $relSt->execute([$b['category'], (int) $b['id']]);
}
$related = $relSt->fetchAll();
if (!$related) {
    $related = db()->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE b.id <> ? ORDER BY b.id LIMIT 3');
    $related->execute([(int) $b['id']]);
    $related = $related->fetchAll();
}
public_head($b['title'] . ' | Online İlahiyat', catalog_seo_excerpt($body));
?>
<main class="mx-auto max-w-6xl px-4 py-12 lg:px-8">
  <div class="grid items-start gap-8 lg:grid-cols-2">
    <?= book_gallery_html($b, 'detail') ?>
    <div>
      <p class="badge"><?= e(book_category_name($b)) ?></p>
      <?php if ($camp): ?>
        <p class="mt-3"><span class="badge"><?= e(campaign_badge_text($camp)) ?> · <?= e((string) $camp['title']) ?></span></p>
      <?php endif; ?>
      <h1 class="font-display mt-4 text-4xl md:text-5xl"><?= e($b['title']) ?></h1>
      <p class="mt-2 text-lg text-muted"><?= e($b['author']) ?></p>
      <p class="mt-6"><span class="price-old mr-2 text-lg"><?= money((int) $b['price_old']) ?></span><span class="price-now text-3xl"><?= money((int) $b['price']) ?></span></p>
      <button data-add-book="<?= (int) $b['id'] ?>" class="btn-primary mt-6">Sepete ekle</button>
      <p class="mt-3 text-sm text-muted">Ödeme sepet adımında güvenli kart ile alınır. Bu sayfada kart çekilmez.</p>
    </div>
  </div>

  <div class="mt-12 grid items-start gap-8 lg:grid-cols-[1fr_340px]">
    <div class="grid gap-8">
      <section>
        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-accent">Kitap</p>
        <h2 class="font-display mt-2 text-3xl">Açıklama</h2>
        <div class="mt-4 grid gap-4 text-lg leading-relaxed text-muted">
          <?php foreach ($paras as $para): ?>
            <p><?= e($para) ?></p>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card overflow-hidden">
        <div class="bg-soft px-6 py-4">
          <h2 class="font-display text-2xl">Özellikler</h2>
        </div>
        <table class="table">
          <tbody>
            <tr><td class="text-muted">Yazar</td><td class="font-extrabold"><?= e($b['author']) ?></td></tr>
            <tr><td class="text-muted">Yayınevi</td><td class="font-extrabold"><?= e($publisher) ?></td></tr>
            <tr><td class="text-muted">Kategori</td><td class="font-extrabold"><a class="text-navy" href="<?= e(kitaplar_url(book_category_slug($b))) ?>"><?= e(book_category_name($b)) ?></a></td></tr>
            <tr><td class="text-muted">Sayfa</td><td class="font-extrabold"><?= $pages > 0 ? (int) $pages . ' sayfa' : 'Belirtilmedi' ?></td></tr>
            <tr><td class="text-muted">Biçim</td><td class="font-extrabold"><?= $digital ? 'Dijital PDF' : 'Basılı' ?></td></tr>
            <tr><td class="text-muted">Stok</td><td class="font-extrabold"><?= $digital ? 'Anında teslim' : (int) $b['stock'] . ' adet' ?></td></tr>
          </tbody>
        </table>
      </section>

      <?php render_installment_table((int) $b['price']); ?>

      <div class="card p-6">
        <h2 class="font-display text-2xl">Kargo</h2>
        <?php if ($digital): ?>
          <p class="mt-2 text-sm text-muted">Bu ürün dijitaldir; kargo ücreti yoktur. Ödeme onayından sonra mağaza hesabınızdaki Kitaplarım bölümüne düşer.</p>
        <?php else: ?>
          <p class="mt-2 text-sm text-muted">500₺ ve üzeri siparişlerde kargo ücretsizdir. Altında sepet özetine 49₺ kargo eklenir. Dijital teslim seçilirse kargo alınmaz.</p>
        <?php endif; ?>
      </div>
    </div>

    <aside class="grid gap-4 lg:sticky lg:top-24">
      <div class="card p-6">
        <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= $digital ? 'Dijital' : 'Basılı' ?></p>
        <p class="font-display mt-2 text-3xl"><?= money((int) $b['price']) ?></p>
        <button data-add-book="<?= (int) $b['id'] ?>" class="btn-primary mt-4 w-full">Sepete ekle</button>
        <a href="<?= e(page_url('sepet')) ?>" class="btn-outline mt-3 w-full">Sepete bak</a>
      </div>
    </aside>
  </div>

  <?php if ($related): ?>
  <section class="mt-14">
    <h2 class="font-display text-3xl">Benzer kitaplar</h2>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($related as $r): ?>
      <article class="card overflow-hidden">
        <?= book_gallery_html($r, 'card', page_url('kitap', (string) $r['slug'])) ?>
        <div class="p-4">
          <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-navy"><?= e(book_category_name($r)) ?></p>
          <h3 class="mt-1 font-extrabold"><?= e($r['title']) ?></h3>
          <p class="text-sm text-muted"><?= e($r['author']) ?></p>
          <p class="mt-2"><span class="price-old mr-2"><?= money((int) $r['price_old']) ?></span><span class="price-now"><?= money((int) $r['price']) ?></span></p>
          <button data-add-book="<?= (int) $r['id'] ?>" class="btn-primary mt-3 w-full text-sm">Sepete ekle</button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php public_foot();
