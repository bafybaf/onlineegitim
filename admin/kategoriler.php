<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$edit = $id > 0 ? shop_category_by_id($id) : null;
if ($id > 0 && !$edit) {
    flash_error('Kategori bulunamadı.');
    redirect('admin/kategoriler');
}
$form = $edit ?: [];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'delete') {
        $delId = (int) post('id');
        $cntSt = db()->prepare('SELECT COUNT(*) FROM books WHERE category_id = ?');
        $cntSt->execute([$delId]);
        if ((int) $cntSt->fetchColumn() > 0) {
            flash_error('Bu kategoride ürün var. Önce ürünleri taşıyın.');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$delId]);
            flash_ok('Kategori silindi.');
        }
        redirect('admin/kategoriler');
    }
    $name = post('name');
    $slugIn = post('slug');
    $sort = (int) post('sort');
    if ($name === '') {
        $err = 'Kategori adı zorunludur.';
    } else {
        $slug = $slugIn !== '' ? shop_tr_slug($slugIn, 'kategori') : shop_unique_slug($name, 'categories', $edit ? (int) $edit['id'] : null, 'kategori');
        if ($slugIn !== '') {
            $slug = shop_unique_slug($slug, 'categories', $edit ? (int) $edit['id'] : null, 'kategori');
        }
        try {
            if ($edit) {
                db()->prepare('UPDATE categories SET slug=?, name=?, sort=? WHERE id=?')->execute([$slug, $name, $sort, (int) $edit['id']]);
                db()->prepare('UPDATE books SET category=? WHERE category_id=?')->execute([$name, (int) $edit['id']]);
                flash_ok('Kategori kaydedildi.');
            } else {
                db()->prepare('INSERT INTO categories (slug, name, sort) VALUES (?,?,?)')->execute([$slug, $name, $sort]);
                flash_ok('Kategori eklendi.');
            }
            redirect('admin/kategoriler');
        } catch (Throwable $e) {
            $err = 'Kayıt başarısız. Slug benzersiz olmalı.';
        }
    }
}

$rows = shop_categories();
$counts = [];
try {
    foreach (db()->query('SELECT category_id, COUNT(*) n FROM books WHERE category_id IS NOT NULL GROUP BY category_id') as $r) {
        $counts[(int) $r['category_id']] = (int) $r['n'];
    }
} catch (Throwable) {
}
$ok = flash_ok();
$flashErr = flash_error();
panel_head('admin', 'kategoriler', 'Kategoriler | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($flashErr): ?><p class="mb-4 font-bold text-accent"><?= e($flashErr) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<p class="mb-4 text-sm text-muted">Mağaza kategorileri. Tutup sürükleyerek sitedeki filtre sırasını değiştirin.</p>

<div class="grid gap-6 lg:grid-cols-[1fr_360px]">
  <div class="card overflow-hidden">
    <?php if (!$rows): ?>
      <p class="dash-empty px-5 py-10">Henüz kategori yok.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th></th><th>Kategori</th><th>Ürün</th><th>Sıra</th><th></th></tr></thead>
        <tbody data-sort-table="categories">
          <?php foreach ($rows as $c): ?>
            <tr data-sort-id="<?= (int) $c['id'] ?>">
              <td class="sort-handle">⋮⋮</td>
              <td>
                <a class="font-extrabold text-navy" href="<?= e(url('admin/kategoriler') . '?id=' . (int) $c['id']) ?>"><?= e((string) $c['name']) ?></a>
                <span class="block text-xs text-muted"><?= e((string) $c['slug']) ?></span>
              </td>
              <td><?= (int) ($counts[(int) $c['id']] ?? 0) ?></td>
              <td><?= (int) $c['sort'] ?></td>
              <td class="whitespace-nowrap">
                <a class="font-extrabold text-navy" href="<?= e(url('admin/kategoriler') . '?id=' . (int) $c['id']) ?>">Düzenle</a>
                <span class="text-muted"> · </span>
                <a class="font-extrabold text-navy" href="<?= e(kitaplar_url((string) $c['slug'])) ?>">Sitede gör</a>
                <form method="post" class="mt-1 inline" onsubmit="return confirm('Kategoriyi silmek istiyor musunuz?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="text-xs font-extrabold text-accent">Sil</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <form method="post" class="card grid gap-3 p-5 h-fit">
    <p class="stat-label"><?= $edit ? 'Düzenle' : 'Yeni kategori' ?></p>
    <h2 class="font-display text-xl"><?= $edit ? e((string) $edit['name']) : 'Kategori ekle' ?></h2>
    <label class="text-sm font-bold">Ad
      <input name="name" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($form['name'] ?? post('name'))) ?>">
    </label>
    <label class="text-sm font-bold">Slug
      <input name="slug" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($form['slug'] ?? post('slug'))) ?>" placeholder="Boşsa addan üretilir">
    </label>
    <label class="text-sm font-bold">Sıra
      <input name="sort" type="number" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= (int) ($form['sort'] ?? 10) ?>">
    </label>
    <button class="btn-primary"><?= $edit ? 'Kaydet' : 'Ekle' ?></button>
    <?php if ($edit): ?>
      <a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/kategoriler')) ?>">Yeni kategori</a>
    <?php endif; ?>
  </form>
</div>
<?php panel_foot();
