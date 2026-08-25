<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$mine = db()->prepare("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id JOIN enrollments e ON e.group_id=r.group_id AND e.student_id=? AND (e.status='aktif' OR e.status IS NULL) AND (e.expires_at IS NULL OR e.expires_at > NOW()) WHERE r.status='live'");
$mine->execute([$u['id']]);
$mine = $mine->fetchAll();
$ids = array_column($mine, 'id') ?: [0];
$others = db()->query("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id WHERE r.status='live' AND r.id NOT IN (" . implode(',', array_map('intval', $ids)) . ")")->fetchAll();
panel_head('ogrenci', 'canli', 'Canlı dersler | Öğrenci Paneli', $u);
membership_panel_banner($u);
?>
<p class="mb-4 text-sm text-muted">Aynı anda birden çok oda açık kalır. Sadece kayıtlı olduğunuz gruplara girebilirsiniz.</p>
<h2 class="font-display text-2xl">Sizin açık odalarınız</h2>
<div class="mt-3 grid gap-3">
<?php foreach ($mine as $r):
    $can = !function_exists('student_can_join_live') || student_can_join_live((int) $u['id'], (int) $r['group_id']);
    ?>
  <div class="card flex flex-wrap items-center justify-between gap-3 p-5"><?= live_pill($r) ?><div><p class="font-extrabold"><?= e($r['title']) ?> — <?= e($r['topic']) ?></p><p class="text-sm text-muted"><?= e($r['teacher_name']) ?></p></div>
  <?php if ($can): ?>
  <a class="btn-primary" href="<?= e(canli_url((int) $r['id'])) ?>">Gir</a>
  <?php else: ?>
  <a class="btn-outline" href="<?= e(url('ogrenci/kayitlar')) ?>">Kayıttan izle</a>
  <?php endif; ?>
  </div>
<?php endforeach; if (!$mine) echo '<p class="text-muted">Açık oda yok.</p>'; ?>
</div>
<h2 class="font-display mt-8 text-2xl">Diğer hocaların canlı dersleri</h2>
<div class="mt-3 grid gap-3">
<?php foreach ($others as $r): ?>
  <div class="card p-4 opacity-70"><span class="live-pill"><i></i> Canlı</span> <b><?= e($r['title']) ?></b> · <?= e($r['teacher_name']) ?></div>
<?php endforeach; ?>
</div>
<?php panel_foot();