<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
shop_books_require_visible();
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
$stock = (int) ($b['stock'] ?? 0);
$inStock = $digital || $stock > 0;
$price = (int) $b['price'];
$priceOld = (int) $b['price_old'];
$off = book_discount_percent($priceOld, $price);
$camp = campaign_for_book($b);
$campNote = '';
if ($camp) {
    $ctype = (string) ($camp['type'] ?? '');
    if ($ctype === 'kargo') {
        $campNote = 'Ücretsiz kargo';
    } elseif ($ctype === 'tutar') {
        $campNote = campaign_badge_text($camp);
    }
}
$catName = book_category_name($b);
$catSlug = book_category_slug($b);
$catId = (int) ($b['category_id'] ?? 0);
$relatedFromCat = false;
if ($catId > 0) {
    $relSt = db()->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE b.category_id = ? AND b.id <> ? ORDER BY b.id LIMIT 4');
    $relSt->execute([$catId, (int) $b['id']]);
} else {
    $relSt = db()->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE b.category = ? AND b.id <> ? ORDER BY b.id LIMIT 4');
    $relSt->execute([$b['category'], (int) $b['id']]);
}
$related = $relSt->fetchAll();
if ($related) {
    $relatedFromCat = true;
} else {
    $related = db()->prepare('SELECT b.*, c.name AS category_name, c.slug AS category_slug FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE b.id <> ? ORDER BY b.id LIMIT 4');
    $related->execute([(int) $b['id']]);
    $related = $related->fetchAll();
}
$maxQty = $digital ? 1 : min(9, max(1, $stock));
$coverSrc = book_cover_src($b);
$avail = $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => (string) $b['title'],
    'description' => catalog_seo_excerpt($body),
    'sku' => 'KITAP-' . (int) $b['id'],
    'brand' => ['@type' => 'Brand', 'name' => $publisher !== '' ? $publisher : 'Online İlahiyat'],
    'offers' => [
        '@type' => 'Offer',
        'url' => canonical_url(),
        'priceCurrency' => 'TRY',
        'price' => $price,
        'availability' => $avail,
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
];
if ($coverSrc !== '') {
    $jsonLd['image'] = [seo_image_url($coverSrc)];
}
if ((string) $b['author'] !== '') {
    $jsonLd['author'] = ['@type' => 'Person', 'name' => (string) $b['author']];
}
public_head($b['title'] . ' | Online İlahiyat', catalog_seo_excerpt($body));
?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<main class="pdp mx-auto max-w-6xl px-4 py-8 lg:px-8 lg:py-10" data-pdp>
  <nav class="pdp-crumb" aria-label="Sayfa konumu">
    <a href="<?= e(kitaplar_url()) ?>">Kitaplar</a>
    <?php if ($catSlug !== '' && $catName !== ''): ?>
      <span>/</span>
      <a href="<?= e(kitaplar_url($catSlug)) ?>"><?= e($catName) ?></a>
    <?php endif; ?>
    <span>/</span>
    <span><?= e($b['title']) ?></span>
  </nav>

  <div class="pdp-top">
    <?= book_pdp_gallery_html($b) ?>

    <div class="pdp-buy">
      <?php if ($campNote !== ''): ?>
        <p class="pdp-flag"><?= e($campNote) ?></p>
      <?php endif; ?>
      <h1 class="pdp-title"><?= e($b['title']) ?></h1>
      <?php if (trim((string) $b['author']) !== ''): ?>
        <p class="pdp-author"><?= e($b['author']) ?></p>
      <?php endif; ?>
      <?php if ($publisher !== ''): ?>
        <p class="pdp-pub"><?= e($publisher) ?></p>
      <?php endif; ?>

      <div class="pdp-price-row">
        <?php if ($off > 0): ?>
          <span class="pdp-price-old"><?= e(money($priceOld)) ?></span>
        <?php endif; ?>
        <span class="pdp-price"><?= e(money($price)) ?></span>
        <?php if ($off > 0): ?>
          <span class="pdp-off">%<?= (int) $off ?></span>
        <?php endif; ?>
      </div>

      <p class="pdp-stock <?= $inStock ? 'is-on' : 'is-off' ?>">
        <?php if ($digital): ?>
          Dijital teslim
        <?php elseif ($inStock): ?>
          Stokta
        <?php else: ?>
          Tükendi
        <?php endif; ?>
      </p>

      <?php if ($inStock): ?>
      <div class="pdp-actions">
        <div class="pdp-actions-row">
          <?php if (!$digital): ?>
          <div class="pdp-qty" data-qty-box>
            <button type="button" data-qty-down aria-label="Azalt">−</button>
            <input type="number" data-book-qty min="1" max="<?= (int) $maxQty ?>" value="1" inputmode="numeric">
            <button type="button" data-qty-up aria-label="Artır">+</button>
          </div>
          <?php endif; ?>
          <button type="button" class="btn-primary pdp-add" data-add-book="<?= (int) $b['id'] ?>">Sepete ekle</button>
        </div>
        <button type="button" class="btn-outline pdp-buy-now" data-buy-book="<?= (int) $b['id'] ?>" data-cart="<?= e(page_url('sepet')) ?>">Satın al</button>
      </div>
      <?php else: ?>
      <p class="pdp-oos">Bu kitap şu an satışta değil.</p>
      <?php endif; ?>

      <ul class="pdp-ship">
        <?php if ($digital): ?>
          <li>Kargo yok. Ödeme sonrası Mağaza hesabınızdaki Kitaplarım bölümünden indirilir.</li>
        <?php else: ?>
          <li>Kargo 49 TL. 500 TL ve üzeri siparişlerde kargo ücretsizdir.</li>
          <li>Teslimden itibaren 14 gün cayma hakkı. Ayrıntı: <a href="<?= e(page_url('iptal-iade')) ?>">İptal ve iade</a></li>
        <?php endif; ?>
      </ul>

      <dl class="pdp-facts">
        <?php if (trim((string) $b['author']) !== ''): ?>
        <div><dt>Yazar</dt><dd><?= e($b['author']) ?></dd></div>
        <?php endif; ?>
        <?php if ($publisher !== ''): ?>
        <div><dt>Yayınevi</dt><dd><?= e($publisher) ?></dd></div>
        <?php endif; ?>
        <?php if ($catName !== ''): ?>
        <div><dt>Kategori</dt><dd><a href="<?= e(kitaplar_url($catSlug)) ?>"><?= e($catName) ?></a></dd></div>
        <?php endif; ?>
        <?php if ($pages > 0): ?>
        <div><dt>Sayfa</dt><dd><?= (int) $pages ?></dd></div>
        <?php endif; ?>
        <div><dt>Biçim</dt><dd><?= $digital ? 'Dijital PDF' : 'Basılı' ?></dd></div>
      </dl>
    </div>
  </div>

  <?php if ($paras): ?>
  <section class="pdp-desc">
    <h2>Ürün açıklaması</h2>
    <?php foreach ($paras as $para): ?>
      <p><?= e($para) ?></p>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <section class="pdp-more">
    <h2><?= $relatedFromCat ? 'Bu kategorideki diğer kitaplar' : 'Diğer kitaplar' ?></h2>
    <div class="pdp-more-grid">
      <?php foreach ($related as $r):
          $rOff = book_discount_percent((int) $r['price_old'], (int) $r['price']);
          $href = page_url('kitap', (string) $r['slug']); ?>
      <article class="pdp-card">
        <a href="<?= e($href) ?>" class="pdp-card-cover"><span class="book-cover-wrap"><?= book_cover_html($r) ?></span></a>
        <div class="pdp-card-body">
          <h3><a href="<?= e($href) ?>"><?= e($r['title']) ?></a></h3>
          <p><?= e($r['author']) ?></p>
          <p class="pdp-card-price">
            <?php if ($rOff > 0): ?><span class="price-old"><?= money((int) $r['price_old']) ?></span><?php endif; ?>
            <span><?= money((int) $r['price']) ?></span>
          </p>
          <button type="button" data-add-book="<?= (int) $r['id'] ?>" class="btn-outline pdp-card-add">Sepete ekle</button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php public_foot();
