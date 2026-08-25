<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$err = '';
$edit = (int) ($_GET['id'] ?? 0);
$row = ['id' => 0, 'title' => '', 'body' => '', 'published' => 1, 'slug' => ''];
if ($edit > 0) {
    $st = db()->prepare('SELECT * FROM posts WHERE id=?');
    $st->execute([$edit]);
    $found = $st->fetch();
    if ($found) {
        $row = $found;
    }
}
if (post('delete_id')) {
    db()->prepare('DELETE FROM posts WHERE id=?')->execute([(int) post('delete_id')]);
    flash_ok('Yazı silindi.');
    redirect('admin/yazilar');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('title')) {
    $title = post('title');
    $body = post('body');
    $pub = isset($_POST['published']) ? 1 : 0;
    if ($title === '' || $body === '') {
        $err = 'Başlık ve metin zorunlu.';
    } else {
        if ((int) post('id') > 0) {
            $pid = (int) post('id');
            db()->prepare('UPDATE posts SET title=?, body=?, published=? WHERE id=?')->execute([$title, $body, $pub, $pid]);
        } else {
            $slug = academy_unique_slug('posts', $title);
            db()->prepare('INSERT INTO posts (slug, title, body, published) VALUES (?,?,?,?)')->execute([$slug, $title, $body, $pub]);
        }
        flash_ok('Yazı kaydedildi.');
        redirect('admin/yazilar');
    }
}
$ok = flash_ok();
$list = db()->query('SELECT * FROM posts ORDER BY ' . catalog_order_sql('', 'posts'))->fetchAll();
panel_head('admin', 'yazilar', 'Duyurular | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card mb-6 grid gap-3 p-5">
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  <label class="text-sm font-bold">Başlık<input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $row['title']) ?>"></label>
  <label class="text-sm font-bold">Metin<textarea name="body" rows="6" required class="mt-1 w-full rounded-xl border px-3 py-2"><?= e((string) $row['body']) ?></textarea></label>
  <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="published" value="1" <?= (int) $row['published'] ? 'checked' : '' ?>> Yayınla</label>
  <button class="btn-primary"><?= (int) $row['id'] ? 'Güncelle' : 'Yazı ekle' ?></button>
</form>
<div data-sort-table="posts">
<?php foreach ($list as $p): ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5" data-sort-id="<?= (int) $p['id'] ?>">
    <span class="sort-handle" title="Sürükleyerek sıralayın">⋮⋮</span>
    <div>
      <p class="font-extrabold"><?= e($p['title']) ?></p>
      <p class="text-sm text-muted"><?= (int) $p['published'] ? 'Yayında' : 'Taslak' ?> · <?= e($p['created_at']) ?></p>
    </div>
    <div class="flex gap-2">
      <a class="btn-outline text-sm" href="<?= e(url('admin/yazilar.php?id=' . (int) $p['id'])) ?>">Düzenle</a>
      <a class="btn-outline text-sm" href="<?= e(page_url('blog', (string) $p['slug'])) ?>">Gör</a>
      <form method="post" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="delete_id" value="<?= (int) $p['id'] ?>"><button class="btn-outline text-sm">Sil</button></form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php panel_foot();
