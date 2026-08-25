<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$id = (int) ($_GET['id'] ?? 0);
$isNew = $id < 1;
$b = [
    'id' => 0,
    'slug' => '',
    'title' => '',
    'author' => '',
    'category' => '',
    'category_id' => 0,
    'price' => 0,
    'price_old' => 0,
    'color' => '#1a3fad',
    'cover' => '',
    'stock' => 0,
    'is_digital' => 0,
    'description' => '',
    'pages' => 0,
    'publisher' => '',
];
if (!$isNew) {
    $st = db()->prepare('SELECT * FROM books WHERE id = ?');
    $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) {
        redirect('admin/urunler');
    }
    $b = $found;
}

$cats = shop_categories();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'sil' && !$isNew) {
        try {
            admin_delete_book($id);
            flash_ok('Ürün silindi.');
            redirect('admin/urunler');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    } else {
    $title = post('title');
    $slugIn = post('slug');
    $author = post('author');
    $categoryId = (int) post('category_id');
    $description = post('description');
    $publisher = post('publisher');
    $price = max(1, (int) post('price'));
    $priceOld = max(0, (int) post('price_old'));
    $stock = max(0, (int) post('stock'));
    $pages = max(0, (int) post('pages'));
    $digital = post('is_digital') === '1' ? 1 : 0;
    if ($title === '') {
        $err = 'Başlık zorunludur.';
    } elseif ($categoryId < 1) {
        $err = 'Kategori seçin.';
    } else {
        $categoryName = shop_sync_book_category($categoryId);
        if ($categoryName === '') {
            $err = 'Kategori bulunamadı.';
        } else {
            $slug = $slugIn !== ''
                ? shop_unique_slug($slugIn, 'books', $isNew ? null : $id, 'urun')
                : shop_unique_slug($title, 'books', $isNew ? null : $id, 'urun');
            try {
                if ($isNew) {
                    db()->prepare(
                        'INSERT INTO books (slug, title, author, category, category_id, description, publisher, price, price_old, stock, pages, is_digital, cover, color)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $slug,
                        $title,
                        $author,
                        $categoryName,
                        $categoryId,
                        $description !== '' ? $description : null,
                        $publisher !== '' ? $publisher : null,
                        $price,
                        $priceOld,
                        $stock,
                        $pages > 0 ? $pages : null,
                        $digital,
                        null,
                        '#1a3fad',
                    ]);
                    $id = (int) db()->lastInsertId();
                    media_attach_uploads('book', $id, 'images', $slug);
                    flash_ok('Ürün eklendi.');
                    redirect('admin/urun/' . $id);
                } else {
                    db()->prepare(
                        'UPDATE books SET slug=?, title=?, author=?, category=?, category_id=?, description=?, publisher=?, price=?, price_old=?, stock=?, pages=?, is_digital=? WHERE id=?'
                    )->execute([
                        $slug,
                        $title,
                        $author,
                        $categoryName,
                        $categoryId,
                        $description !== '' ? $description : null,
                        $publisher !== '' ? $publisher : null,
                        $price,
                        $priceOld,
                        $stock,
                        $pages > 0 ? $pages : null,
                        $digital,
                        $id,
                    ]);
                    media_attach_uploads('book', $id, 'images', $slug);
                    flash_ok('Ürün kaydedildi.');
                    redirect('admin/urun/' . $id);
                }
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
    $b['title'] = $title;
    $b['slug'] = $slugIn;
    $b['author'] = $author;
    $b['category_id'] = $categoryId;
    $b['description'] = $description;
    $b['publisher'] = $publisher;
    $b['price'] = $price;
    $b['price_old'] = $priceOld;
    $b['stock'] = $stock;
    $b['pages'] = $pages;
    $b['is_digital'] = $digital;
    }
}

$ok = flash_ok();
panel_head('admin', 'urunler', ($isNew ? 'Yeni ürün' : 'Ürün düzenle') . ' | Admin', $u);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/urunler')) ?>">← Ürünler</a></p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-[1fr_260px]">
  <form method="post" enctype="multipart/form-data" class="card grid gap-4 p-6">
    <label class="text-sm font-bold">Başlık
      <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $b['title']) ?>">
    </label>
    <label class="text-sm font-bold">Slug
      <input name="slug" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $b['slug']) ?>" placeholder="Boş bırakılırsa başlıktan üretilir">
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-bold">Yazar
        <input name="author" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $b['author']) ?>">
      </label>
      <label class="text-sm font-bold">Kategori
        <select name="category_id" required class="mt-1 w-full rounded-xl border px-3 py-2">
          <option value="">Seçin</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($b['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <label class="text-sm font-bold">Yayınevi
      <input name="publisher" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($b['publisher'] ?? '')) ?>">
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-bold">Fiyat
        <input name="price" type="number" min="1" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= (int) $b['price'] ?>">
      </label>
      <label class="text-sm font-bold">Eski fiyat
        <input name="price_old" type="number" min="0" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= (int) $b['price_old'] ?>">
      </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-bold">Stok
        <input name="stock" type="number" min="0" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= (int) $b['stock'] ?>">
      </label>
      <label class="text-sm font-bold">Sayfa
        <input name="pages" type="number" min="0" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= (int) ($b['pages'] ?? 0) ?>">
      </label>
    </div>
    <label class="flex items-center gap-2 text-sm font-bold">
      <input type="checkbox" name="is_digital" value="1" <?= (int) $b['is_digital'] === 1 ? 'checked' : '' ?>>
      Dijital ürün
    </label>
    <label class="text-sm font-bold">Açıklama
      <textarea name="description" rows="6" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e((string) ($b['description'] ?? '')) ?></textarea>
    </label>
    <label class="text-sm font-bold">Görseller</label>
    <?= media_dropzone_html('book', $isNew ? 0 : $id) ?>
    <p class="text-xs text-muted">JPG, PNG veya WEBP. İlk görsel kapak olur; sürükleyerek sırayı değiştirin.</p>
    <button class="btn-primary"><?= $isNew ? 'Ürünü ekle' : 'Kaydet' ?></button>
  </form>
  <aside class="grid gap-4">
    <?php if (!$isNew): ?>
    <div class="card overflow-hidden">
      <?= book_gallery_html($b, 'card') ?>
      <div class="p-4">
        <p class="stat-label">Galeri</p>
        <p class="mt-1 text-sm text-muted"><?= count(media_items('book', $id)) ?> görsel. İlk kare kapaktır.</p>
      </div>
    </div>
    <a class="btn-outline text-center" href="<?= e(page_url('kitap', (string) $b['slug'])) ?>">Sitede gör</a>
    <?= panel_delete_form(urun_admin_url($id), ['act' => 'sil'], 'Ürün silinsin mi? Siparişte geçen ürün silinmez.', 'Ürünü sil', 'btn-outline text-center') ?>
    <?php else: ?>
    <div class="card p-4 text-sm text-muted">Kayıttan sonra kapak önizlemesi ve sitede gör linki açılır.</div>
    <?php endif; ?>
  </aside>
</div>
<?php panel_foot();
