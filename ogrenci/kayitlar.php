<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$st = db()->prepare(
    "SELECT rec.*, g.name gname, t.name tname
     FROM recordings rec
     JOIN class_groups g ON g.id=rec.group_id
     JOIN users t ON t.id=rec.teacher_id
     JOIN enrollments e ON e.group_id=rec.group_id AND e.student_id=?
     ORDER BY rec.recorded_on DESC, rec.id DESC"
);
$st->execute([(int) $u['id']]);
$rows = $st->fetchAll();
panel_head('ogrenci', 'kayitlar', 'Ders kayıtları | Öğrenci Paneli', $u);
?>
<p class="mb-5 text-sm text-muted">Kayıtlı olduğunuz grupların ders videoları. Canlı ders bittikten sonra hoca buraya yükler.</p>
<?php if (!$rows): ?>
  <div class="card p-5">Henüz izlenecek kayıt yok.</div>
<?php endif; ?>
<?php foreach ($rows as $r):
    $ready = !empty($r['video_path']) || !empty($r['video_url']);
    ?>
  <article class="card mb-3 flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
      <p class="font-extrabold"><?= e($r['title']) ?></p>
      <p class="text-sm text-muted"><?= e($r['gname']) ?> · <?= e($r['tname']) ?> · <?= e($r['recorded_on']) ?> · <?= (int) $r['mins'] ?> dk</p>
    </div>
    <?php if ($ready): ?>
      <a class="btn-primary text-sm" href="<?= e(url('ogrenci/kayit-izle.php?id=' . (int) $r['id'])) ?>">İzle</a>
    <?php else: ?>
      <span class="text-sm text-muted">Video bekleniyor</span>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
<?php panel_foot();
