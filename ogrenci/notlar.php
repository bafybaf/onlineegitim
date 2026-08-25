<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$st = db()->prepare(
    "SELECT n.*, g.name gname, t.name tname
     FROM lesson_notes n
     JOIN class_groups g ON g.id=n.group_id
     JOIN users t ON t.id=n.teacher_id
     JOIN enrollments e ON e.group_id=n.group_id AND e.student_id=?
     ORDER BY n.id DESC"
);
$st->execute([(int) $u['id']]);
$rows = $st->fetchAll();
panel_head('ogrenci', 'notlar', 'Ders notları | Öğrenci Paneli', $u);
?>
<p class="mb-5 text-sm text-muted">Hocalarınızın yüklediği PDF ve görseller. Ödev tesliminden ayrıdır.</p>
<?php if (!$rows): ?><div class="card p-5">Henüz not yok.</div><?php endif; ?>
<?php foreach ($rows as $n): ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
      <p class="font-extrabold"><?= e($n['title']) ?></p>
      <p class="text-sm text-muted"><?= e($n['gname']) ?> · <?= e($n['tname']) ?></p>
    </div>
    <a class="btn-primary text-sm" href="<?= e(url('api/dosya.php?tur=not&id=' . (int) $n['id'])) ?>">İndir</a>
  </article>
<?php endforeach; ?>
<?php panel_foot();
