<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$err = '';
$ok = flash_ok();
$groups = teacher_groups((int) $u['id']);
if (post('delete_id')) {
    try {
        academy_delete_note((int) post('delete_id'), (int) $u['id']);
        flash_ok('Not silindi.');
        redirect('ogretmen/notlar');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('title')) {
    $gid = (int) post('group_id');
    $owns = false;
    foreach ($groups as $g) {
        if ((int) $g['id'] === $gid) {
            $owns = true;
            break;
        }
    }
    if (!$owns) {
        $err = 'Grup seçin.';
    } else {
        try {
            $path = academy_store_upload('file', 'notes', academy_mimes_doc(), 20);
            if ($path === null) {
                throw new RuntimeException('PDF veya görsel yükleyin.');
            }
            db()->prepare('INSERT INTO lesson_notes (group_id, teacher_id, title, file_path) VALUES (?,?,?,?)')
                ->execute([$gid, (int) $u['id'], post('title'), $path]);
            notify_group_students($gid, 'Yeni ders notu', post('title'), url('ogrenci/notlar'));
            flash_ok('Not yüklendi.');
            redirect('ogretmen/notlar');
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
}
$st = db()->prepare(
    'SELECT n.*, g.name gname FROM lesson_notes n JOIN class_groups g ON g.id=n.group_id WHERE n.teacher_id=? ORDER BY n.id DESC'
);
$st->execute([(int) $u['id']]);
$rows = $st->fetchAll();
panel_head('ogretmen', 'notlar', 'Ders notları | Öğretmen Paneli', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card mb-6 grid gap-3 p-5 md:grid-cols-3 md:items-end">
  <label class="text-sm font-bold">Grup
    <select name="group_id" class="mt-1 w-full rounded-xl border px-3 py-2"><?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select>
  </label>
  <label class="text-sm font-bold">Başlık
    <input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2">
  </label>
  <label class="text-sm font-bold">Dosya
    <input name="file" type="file" required accept=".pdf,image/*" class="mt-1 w-full text-sm">
  </label>
  <button class="btn-primary md:col-span-3">Yükle</button>
</form>
<?php foreach ($rows as $n): ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
      <p class="font-extrabold"><?= e($n['title']) ?></p>
      <p class="text-sm text-muted"><?= e($n['gname']) ?> · <?= e($n['created_at']) ?></p>
    </div>
    <a class="btn-outline text-sm" href="<?= e(url('api/dosya.php?tur=not&id=' . (int) $n['id'])) ?>">İndir</a>
    <?= panel_delete_form('', ['delete_id' => (int) $n['id']], 'Bu ders notu silinsin mi?') ?>
  </article>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-muted">Henüz not yok.</p><?php endif; ?>
<?php panel_foot();
