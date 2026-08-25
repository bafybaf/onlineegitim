<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$ok = flash_ok();
$err = flash_error();
if (post('delete_id')) {
    try {
        academy_delete_homework((int) post('delete_id'), (int) $u['id']);
        flash_ok('Ödev silindi.');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }
    redirect('ogretmen/odevler.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('title')) {
        $gid = (int) post('group_id');
        db()->prepare('INSERT INTO homework (group_id, title, due_label, created_by) VALUES (?,?,?,?)')->execute([$gid, post('title'), post('due') ?: 'Bu hafta', $u['id']]);
    $hid = (int) db()->lastInsertId();
    $stu = db()->prepare('SELECT student_id FROM enrollments WHERE group_id=?');
    $stu->execute([$gid]);
    $ins = db()->prepare('INSERT INTO homework_subs (homework_id, student_id, status) VALUES (?,?, "open")');
    foreach ($stu as $row) {
        $ins->execute([$hid, $row['student_id']]);
        notify_user((int) $row['student_id'], 'Yeni ödev', post('title'), url('ogrenci/odevler'));
    }
    redirect('ogretmen/odevler.php');
}
if (post('ok')) {
    db()->prepare("UPDATE homework_subs SET status='ok' WHERE homework_id=? AND student_id=?")->execute([(int) post('hid'), (int) post('sid')]);
    redirect('ogretmen/odevler.php');
}
$groups = db()->prepare('SELECT * FROM class_groups WHERE teacher_id=?');
$groups->execute([$u['id']]);
$groups = $groups->fetchAll();
$gids = array_column($groups, 'id') ?: [0];
$hw = db()->query('SELECT h.*, g.name gname FROM homework h JOIN class_groups g ON g.id=h.group_id WHERE h.group_id IN (' . implode(',', array_map('intval', $gids)) . ') ORDER BY h.id DESC')->fetchAll();
panel_head('ogretmen', 'odevler', 'Ödevler | Öğretmen Paneli', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<form method="post" class="card mb-6 grid gap-3 p-5 md:grid-cols-4 md:items-end">
  <label class="text-sm font-bold">Grup<select name="group_id" class="mt-1 w-full rounded-xl border px-3 py-2"><?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select></label>
  <label class="text-sm font-bold md:col-span-2">Başlık<input name="title" required class="mt-1 w-full rounded-xl border px-3 py-2"></label>
  <button class="btn-primary">Ödev ver</button>
</form>
<?php foreach ($hw as $h):
    $subs = db()->prepare('SELECT s.*, u.name FROM homework_subs s JOIN users u ON u.id=s.student_id WHERE s.homework_id=?');
    $subs->execute([$h['id']]);
?>
<article class="card mb-4 p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <h2 class="font-extrabold"><?= e($h['title']) ?></h2>
      <p class="text-sm text-muted"><?= e($h['gname']) ?> · <?= e($h['due_label']) ?></p>
    </div>
    <?= panel_delete_form('', ['delete_id' => (int) $h['id']], 'Ödev ve teslimler silinsin mi?') ?>
  </div>
<ul class="mt-3 text-sm">
<?php foreach ($subs as $s): ?>
  <li class="flex flex-wrap items-center justify-between gap-2 border-t py-2"><?= e($s['name']) ?> · <?= e($s['status']) ?><?= $s['body'] ? ' · “' . e(mb_strimwidth($s['body'], 0, 40, '…')) . '”' : '' ?>
    <?php if (!empty($s['file_path'])): ?><a class="font-extrabold text-navy" href="<?= e(url('api/dosya.php?tur=odev&id=' . (int) $s['id'])) ?>">Dosya</a><?php endif; ?>
  <?php if ($s['status'] === 'sent'): ?><form method="post"><input type="hidden" name="ok" value="1"><input type="hidden" name="hid" value="<?= (int) $h['id'] ?>"><input type="hidden" name="sid" value="<?= (int) $s['student_id'] ?>"><button class="font-extrabold text-navy">Onayla</button></form><?php endif; ?></li>
<?php endforeach; ?>
</ul></article>
<?php endforeach; panel_foot();