<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stocks = $_POST['stock'] ?? [];
    $prices = $_POST['price'] ?? [];
    if (is_array($stocks)) {
        $updS = db()->prepare('UPDATE books SET stock=? WHERE id=?');
        foreach ($stocks as $bid => $n) {
            $updS->execute([max(0, (int) $n), (int) $bid]);
        }
    }
    if (is_array($prices)) {
        $updP = db()->prepare('UPDATE books SET price=? WHERE id=?');
        foreach ($prices as $bid => $n) {
            $updP->execute([max(1, (int) $n), (int) $bid]);
        }
    }
    flash_ok('Stok ve fiyatlar kaydedildi.');
    redirect('admin/urunler');
}

$books = db()->query(
    'SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON c.id = b.category_id ORDER BY ' . catalog_order_sql('b', 'books')
)->fetchAll();
$ok = flash_ok();
panel_head('admin', 'urunler', 'Ürünler | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-muted">Mağaza ürünleri. Satırları tutup sürükleyerek sırayı değiştirin. Kapak için <b>Düzenle</b>.</p>
  <a class="btn-primary text-sm" href="<?= e(urun_yeni_url()) ?>">Yeni ürün</a>
</div>

<?php if (!$books): ?>
  <div class="card">
    <p class="dash-empty px-5 py-10">Henüz ürün yok. Mağaza kataloğu boş.</p>
  </div>
<?php else: ?>
<form method="post">
  <div class="card overflow-hidden">
    <table class="table">
      <thead>
        <tr>
          <th></th>
          <th>Ürün</th>
          <th>Tür</th>
          <th>Stok</th>
          <th>Fiyat</th>
          <th></th>
        </tr>
      </thead>
      <tbody data-sort-table="books">
        <?php foreach ($books as $b):
            $digital = (int) $b['is_digital'] === 1;
            $low = !$digital && (int) $b['stock'] < 5;
            ?>
          <tr class="<?= $low ? 'is-low' : '' ?>" data-sort-id="<?= (int) $b['id'] ?>">
            <td class="sort-handle" title="Sürükleyerek sıralayın">⋮⋮</td>
            <td>
              <span class="inline-flex items-center gap-3">
                <span class="prod-swatch"><?= book_cover_html($b, '', 'thumb') ?></span>
                <span>
                  <span class="font-extrabold"><?= e((string) $b['title']) ?></span>
                  <span class="block text-xs text-muted"><?= e((string) $b['author']) ?> · <?= e(book_category_name($b)) ?></span>
                </span>
              </span>
            </td>
            <td>
              <?php if ($digital): ?>
                <span class="shop-pill shop-pill-ok">Dijital</span>
              <?php else: ?>
                <span class="shop-pill">Basılı</span>
              <?php endif; ?>
            </td>
            <td>
              <input name="stock[<?= (int) $b['id'] ?>]" type="number" min="0" class="w-20 rounded-lg border px-2 py-1" value="<?= (int) $b['stock'] ?>">
              <?php if ($low): ?><span class="ml-1 text-xs font-extrabold text-accent">Düşük</span><?php endif; ?>
            </td>
            <td>
              <input name="price[<?= (int) $b['id'] ?>]" type="number" min="1" class="w-24 rounded-lg border px-2 py-1" value="<?= (int) $b['price'] ?>">
            </td>
            <td class="whitespace-nowrap">
              <a class="font-extrabold text-navy" href="<?= e(urun_admin_url((int) $b['id'])) ?>">Düzenle</a>
              <span class="text-muted"> · </span>
              <a class="font-extrabold text-navy" href="<?= e(page_url('kitap', (string) $b['slug'])) ?>">Sitede gör</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <button class="btn-primary mt-4">Stok ve fiyatı kaydet</button>
</form>
<?php endif; ?>
<?php panel_foot();
