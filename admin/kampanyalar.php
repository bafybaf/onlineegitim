<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$edit = null;
if ($id > 0) {
    $st = db()->prepare('SELECT * FROM campaigns WHERE id = ?');
    $st->execute([$id]);
    $edit = $st->fetch() ?: null;
    if (!$edit) {
        flash_error('Kampanya bulunamadı.');
        redirect('admin/kampanyalar');
    }
}

$form = $edit ?: [];
$cats = shop_categories();
$books = db()->query('SELECT id, title FROM books ORDER BY title')->fetchAll();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'delete') {
        db()->prepare('DELETE FROM campaigns WHERE id = ?')->execute([(int) post('id')]);
        flash_ok('Kampanya silindi.');
        redirect('admin/kampanyalar');
    }
    $title = post('title');
    $slugIn = post('slug');
    $description = post('description');
    $type = post('type');
    if (!in_array($type, ['yuzde', 'tutar', 'kargo'], true)) {
        $type = 'yuzde';
    }
    $discount = max(0, (int) post('discount_value'));
    $code = strtoupper(trim(post('code')));
    $applies = post('applies_to');
    if (!in_array($applies, ['all', 'category', 'book'], true)) {
        $applies = 'all';
    }
    $categoryId = (int) post('category_id');
    $bookId = (int) post('book_id');
    $starts = post('starts_at');
    $ends = post('ends_at');
    $active = post('active') === '1' ? 1 : 0;
    if ($title === '') {
        $err = 'Başlık zorunludur.';
    } elseif ($type === 'yuzde' && ($discount < 1 || $discount > 90)) {
        $err = 'Yüzde indirim 1–90 arasında olmalı.';
    } elseif ($type === 'tutar' && $discount < 1) {
        $err = 'Tutar indirimi girin.';
    } elseif ($applies === 'category' && $categoryId < 1) {
        $err = 'Kategori seçin.';
    } elseif ($applies === 'book' && $bookId < 1) {
        $err = 'Ürün seçin.';
    } else {
        if ($applies !== 'category') {
            $categoryId = 0;
        }
        if ($applies !== 'book') {
            $bookId = 0;
        }
        $slug = $slugIn !== '' ? shop_unique_slug($slugIn, 'campaigns', $edit ? (int) $edit['id'] : null, 'kampanya') : shop_unique_slug($title, 'campaigns', $edit ? (int) $edit['id'] : null, 'kampanya');
        $codeVal = $code !== '' ? $code : null;
        if ($codeVal !== null) {
            $chk = db()->prepare('SELECT id FROM campaigns WHERE code = ?' . ($edit ? ' AND id <> ?' : ''));
            $chk->execute($edit ? [$codeVal, (int) $edit['id']] : [$codeVal]);
            if ($chk->fetch()) {
                $err = 'Bu kupon kodu başka kampanyada kayıtlı.';
            }
        }
        if ($err === '') {
            $startsSql = $starts !== '' ? str_replace('T', ' ', $starts) . (strlen($starts) === 16 ? ':00' : '') : null;
            $endsSql = $ends !== '' ? str_replace('T', ' ', $ends) . (strlen($ends) === 16 ? ':00' : '') : null;
            try {
                if ($edit) {
                    db()->prepare(
                        'UPDATE campaigns SET title=?, slug=?, description=?, type=?, discount_value=?, code=?, applies_to=?, category_id=?, book_id=?, starts_at=?, ends_at=?, active=? WHERE id=?'
                    )->execute([
                        $title, $slug, $description !== '' ? $description : null, $type, $discount, $codeVal, $applies,
                        $categoryId > 0 ? $categoryId : null, $bookId > 0 ? $bookId : null, $startsSql, $endsSql, $active, (int) $edit['id'],
                    ]);
                    flash_ok('Kampanya kaydedildi.');
                } else {
                    db()->prepare(
                        'INSERT INTO campaigns (title, slug, description, type, discount_value, code, applies_to, category_id, book_id, starts_at, ends_at, active)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $title, $slug, $description !== '' ? $description : null, $type, $discount, $codeVal, $applies,
                        $categoryId > 0 ? $categoryId : null, $bookId > 0 ? $bookId : null, $startsSql, $endsSql, $active,
                    ]);
                    flash_ok('Kampanya eklendi.');
                }
                redirect('admin/kampanyalar');
            } catch (Throwable $e) {
                $err = 'Kayıt başarısız. Slug benzersiz olmalı.';
            }
        }
    }
}

$dt = static function (?string $v): string {
    if ($v === null || $v === '') {
        return '';
    }
    $t = strtotime($v);
    return $t ? date('Y-m-d\TH:i', $t) : '';
};

$rows = [];
try {
    $rows = db()->query(
        'SELECT c.*, cat.name AS category_name, b.title AS book_title
         FROM campaigns c
         LEFT JOIN categories cat ON cat.id = c.category_id
         LEFT JOIN books b ON b.id = c.book_id
         ORDER BY ' . catalog_order_sql('c', 'campaigns')
    )->fetchAll();
} catch (Throwable) {
    $rows = [];
}
$ok = flash_ok();
$flashErr = flash_error();
panel_head('admin', 'kampanyalar', 'Kampanyalar | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($flashErr): ?><p class="mb-4 font-bold text-accent"><?= e($flashErr) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<p class="mb-4 text-sm text-muted">Kupon kodlu kampanyalar sepette girilince uygulanır. Kodsuz olanlar eşleşen ürünlerde otomatik düşer ve sitede rozet/banner gösterir.</p>

<div class="grid gap-6 lg:grid-cols-[1fr_400px]">
  <div class="card overflow-hidden">
    <?php if (!$rows): ?>
      <p class="dash-empty px-5 py-10">Henüz kampanya yok.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th></th><th>Kampanya</th><th>İndirim</th><th>Kapsam</th><th></th></tr></thead>
        <tbody data-sort-table="campaigns">
          <?php foreach ($rows as $c): ?>
            <tr data-sort-id="<?= (int) $c['id'] ?>">
              <td class="sort-handle">⋮⋮</td>
              <td>
                <a class="font-extrabold text-navy" href="<?= e(url('admin/kampanyalar') . '?id=' . (int) $c['id']) ?>"><?= e((string) $c['title']) ?></a>
                <span class="block text-xs text-muted">
                  <?= campaign_is_live($c) ? 'Aktif' : 'Kapalı / tarih dışı' ?>
                  <?php if (!empty($c['code'])): ?> · kupon <?= e((string) $c['code']) ?><?php endif; ?>
                </span>
              </td>
              <td><?= e(campaign_badge_text($c)) ?></td>
              <td class="text-sm">
                <?php if (($c['applies_to'] ?? '') === 'category'): ?>
                  <?= e((string) ($c['category_name'] ?? 'Kategori')) ?>
                <?php elseif (($c['applies_to'] ?? '') === 'book'): ?>
                  <?= e((string) ($c['book_title'] ?? 'Ürün')) ?>
                <?php else: ?>
                  Tüm mağaza
                <?php endif; ?>
              </td>
              <td class="whitespace-nowrap">
                <a class="font-extrabold text-navy" href="<?= e(url('admin/kampanyalar') . '?id=' . (int) $c['id']) ?>">Düzenle</a>
                <form method="post" class="mt-1" onsubmit="return confirm('Kampanyayı silmek istiyor musunuz?');">
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
    <p class="stat-label"><?= $edit ? 'Düzenle' : 'Yeni kampanya' ?></p>
    <h2 class="font-display text-xl"><?= $edit ? e((string) $edit['title']) : 'Kampanya ekle' ?></h2>
    <label class="text-sm font-bold">Başlık
      <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($form['title'] ?? post('title'))) ?>">
    </label>
    <label class="text-sm font-bold">Slug
      <input name="slug" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($form['slug'] ?? post('slug'))) ?>" placeholder="Boşsa başlıktan">
    </label>
    <label class="text-sm font-bold">Açıklama
      <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal"><?= e((string) ($form['description'] ?? post('description'))) ?></textarea>
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
      <label class="text-sm font-bold">Tür
        <select name="type" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
          <?php foreach (['yuzde' => 'Yüzde', 'tutar' => 'Tutar (₺)', 'kargo' => 'Ücretsiz kargo'] as $k => $lab): ?>
            <option value="<?= e($k) ?>" <?= ((($form['type'] ?? post('type')) ?: 'yuzde') === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="text-sm font-bold">Değer
        <input name="discount_value" type="number" min="0" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= (int) ($form['discount_value'] ?? (post('discount_value') !== '' ? post('discount_value') : 10)) ?>">
      </label>
    </div>
    <label class="text-sm font-bold">Kupon kodu
      <input name="code" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal uppercase" value="<?= e((string) ($form['code'] ?? post('code'))) ?>" placeholder="Boş = otomatik / banner">
    </label>
    <label class="text-sm font-bold">Kapsam
      <select name="applies_to" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach (['all' => 'Tüm mağaza', 'category' => 'Kategori', 'book' => 'Tek ürün'] as $k => $lab): ?>
          <option value="<?= e($k) ?>" <?= ((($form['applies_to'] ?? post('applies_to')) ?: 'all') === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Kategori
      <select name="category_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="0">—</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) ($form['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Ürün
      <select name="book_id" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <option value="0">—</option>
        <?php foreach ($books as $b): ?>
          <option value="<?= (int) $b['id'] ?>" <?= (int) ($form['book_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>><?= e((string) $b['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
      <label class="text-sm font-bold">Başlangıç
        <input name="starts_at" type="datetime-local" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e($dt($form['starts_at'] ?? null)) ?>">
      </label>
      <label class="text-sm font-bold">Bitiş
        <input name="ends_at" type="datetime-local" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e($dt($form['ends_at'] ?? null)) ?>">
      </label>
    </div>
    <label class="flex items-center gap-2 text-sm font-bold">
      <input type="checkbox" name="active" value="1" <?= (int) ($form['active'] ?? 1) === 1 ? 'checked' : '' ?>>
      Aktif
    </label>
    <button class="btn-primary"><?= $edit ? 'Kaydet' : 'Ekle' ?></button>
    <?php if ($edit): ?>
      <a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/kampanyalar')) ?>">Yeni kampanya</a>
    <?php endif; ?>
  </form>
</div>
<?php panel_foot();
