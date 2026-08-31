<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$id = (int) ($_GET['id'] ?? 0);
$p = [
    'id' => 0,
    'slug' => '',
    'title' => '',
    'level' => '',
    'hours' => '',
    'tag' => '',
    'description' => '',
    'price_old' => 0,
    'price_now' => 0,
    'image' => '',
];
if ($id > 0) {
    $st = db()->prepare('SELECT * FROM programs WHERE id = ?');
    $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) {
        redirect('admin/programlar');
    }
    $p = $found;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('act') === 'sil' && $id > 0) {
        try {
            admin_delete_program($id);
            flash_ok('Program silindi.');
            redirect('admin/programlar');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    } else {
        $title = post('title');
        $level = post('level');
        $hours = post('hours');
        $tag = post('tag');
        $description = post('description');
        $priceOld = max(0, (int) post('price_old'));
        $priceNow = max(0, (int) post('price_now'));
        if ($title === '' || $description === '') {
            $err = 'Başlık ve açıklama zorunludur.';
        } else {
            try {
                $slug = $id > 0 ? (string) $p['slug'] : academy_unique_slug('programs', $title);
                if ($id < 1) {
                    db()->prepare(
                        'INSERT INTO programs (slug, title, level, hours, tag, description, price_old, price_now, image) VALUES (?,?,?,?,?,?,?,?,?)'
                    )->execute([$slug, $title, $level, $hours, $tag, $description, $priceOld, $priceNow, null]);
                    $id = (int) db()->lastInsertId();
                    media_attach_uploads('program', $id, 'images', $slug);
                    media_attach_uploads('program_body', $id, 'body_images', $slug);
                    flash_ok('Program oluşturuldu.');
                } else {
                    $prevNow = (int) ($p['price_now'] ?? 0);
                    db()->prepare(
                        'UPDATE programs SET title=?, level=?, hours=?, tag=?, description=?, price_old=?, price_now=? WHERE id=?'
                    )->execute([$title, $level, $hours, $tag, $description, $priceOld, $priceNow, $id]);
                    if ($prevNow !== $priceNow) {
                        db()->prepare("UPDATE packages SET price = ? WHERE kind = 'ders' AND program_id = ? AND price = ?")
                            ->execute([$priceNow, $id, $prevNow]);
                    }
                    media_attach_uploads('program', $id, 'images', $slug);
                    media_attach_uploads('program_body', $id, 'body_images', $slug);
                    flash_ok('Program kaydedildi.');
                }
                redirect('admin/program.php?id=' . $id);
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
        $p['title'] = $title;
        $p['level'] = $level;
        $p['hours'] = $hours;
        $p['tag'] = $tag;
        $p['description'] = $description;
        $p['price_old'] = $priceOld;
        $p['price_now'] = $priceNow;
    }
}

$ok = flash_ok();
$isNew = $id < 1;
panel_head('admin', 'programlar', ($isNew ? 'Yeni program' : 'Program düzenle') . ' | Admin', $u);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/programlar')) ?>">← Programlar</a></p>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>

<div class="grid gap-6 lg:grid-cols-[1fr_280px]">
  <form method="post" enctype="multipart/form-data" class="card grid gap-4 p-6">
    <label class="text-sm font-bold">Başlık
      <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $p['title']) ?>">
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-bold">Seviye
        <input name="level" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $p['level']) ?>">
      </label>
      <label class="text-sm font-bold">Süre
        <input name="hours" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $p['hours']) ?>">
      </label>
    </div>
    <label class="text-sm font-bold">Etiket
      <input name="tag" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $p['tag']) ?>">
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-sm font-bold">Eski fiyat
        <input name="price_old" type="number" min="0" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= (int) $p['price_old'] ?>">
      </label>
      <label class="text-sm font-bold">Güncel fiyat
        <input name="price_now" type="number" min="0" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= (int) $p['price_now'] ?>">
      </label>
    </div>
    <p class="text-xs text-muted">Güncel fiyat 0 ise sitede “Ücretsiz” yazılır.</p>
    <label class="text-sm font-bold">Açıklama
      <textarea name="description" rows="6" class="mt-1 w-full rounded-xl border px-3 py-2"><?= e((string) $p['description']) ?></textarea>
    </label>
    <label class="text-sm font-bold">Açıklama görselleri</label>
    <?= media_dropzone_html('program_body', $isNew ? 0 : $id, 'body_images') ?>
    <p class="text-xs text-muted">Program hakkında bölümünde metnin altında görünür. Birden fazla yükleyebilirsiniz.</p>
    <label class="text-sm font-bold">Kapak görselleri</label>
    <?= media_dropzone_html('program', $isNew ? 0 : $id) ?>
    <p class="text-xs text-muted">JPG, PNG veya WEBP. Üstteki slayt için birden fazla yükleyin; sürükleyerek sırayı değiştirin.</p>
    <button class="btn-primary"><?= $isNew ? 'Oluştur' : 'Kaydet' ?></button>
  </form>
  <aside class="grid gap-4">
    <?php if (!$isNew): ?>
    <div class="card overflow-hidden">
      <?= program_gallery_html($p, 'card') ?>
      <div class="p-4">
        <p class="stat-label">Galeri</p>
        <p class="mt-1 text-sm text-muted"><?= count(media_items('program', $id)) ?> görsel</p>
      </div>
    </div>
    <a class="btn-outline text-center" href="<?= e(page_url('program', (string) $p['slug'])) ?>">Sitede gör</a>
    <a class="btn-outline text-center" href="<?= e(url('admin/gruplar')) ?>">Gruplar</a>
    <?= panel_delete_form(program_admin_url($id), ['act' => 'sil'], 'Program silinsin mi? Bağlı grup varsa silinmez.', 'Programı sil', 'btn-outline text-center') ?>
    <?php else: ?>
    <p class="text-sm text-muted">Kayıttan sonra kapak ve genel sayfa linki görünür.</p>
    <?php endif; ?>
  </aside>
</div>
<?php panel_foot();
