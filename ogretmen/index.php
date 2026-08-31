<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$groups = db()->prepare('SELECT * FROM class_groups WHERE teacher_id=?');
$groups->execute([$u['id']]);
$groups = $groups->fetchAll();
$mine = db()->prepare("SELECT * FROM live_rooms WHERE teacher_id=? AND status='live'");
$mine->execute([$u['id']]);
$mine = $mine->fetchAll();
$others = db()->prepare("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id WHERE r.status='live' AND r.teacher_id<>?");
$others->execute([$u['id']]);
$nStu = db()->prepare('SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN class_groups g ON g.id=e.group_id WHERE g.teacher_id=?');
$nStu->execute([$u['id']]);
$allLive = (int) db()->query("SELECT COUNT(*) FROM live_rooms WHERE status='live'")->fetchColumn();
$qPending = function_exists('question_teacher_pending_count') ? question_teacher_pending_count((int) $u['id']) : 0;
panel_head('ogretmen', 'dashboard', 'Özet | Öğretmen Paneli', $u);
?>
<div class="grid gap-4 md:grid-cols-5">
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Sınıf</p><p class="font-display mt-1 text-2xl"><?= count($groups) ?> grup</p></div>
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Öğrenci</p><p class="font-display mt-1 text-2xl"><?= (int) $nStu->fetchColumn() ?></p></div>
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Sizin açık odalar</p><p class="font-display mt-1 text-2xl"><?= count($mine) ?></p></div>
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Sistemde eşzamanlı</p><p class="font-display mt-1 text-2xl"><?= $allLive ?></p></div>
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Bekleyen soru</p><p class="font-display mt-1 text-2xl"><?= $qPending ?></p><a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e(url('ogretmen/sorular')) ?>">Sorular →</a></div>
</div>
<div class="mt-6 grid gap-4 lg:grid-cols-2">
  <div class="card p-5">
    <h2 class="font-display text-2xl">Sizin canlı odalarınız</h2>
    <p class="mt-1 text-sm text-muted">İkinci grubu da açabilirsiniz; diğer oda kapanmaz.</p>
    <?php foreach ($mine as $r): ?>
      <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t pt-3"><?= live_pill($r) ?> <b><?= e($r['title']) ?></b> <a class="btn-primary text-sm" href="<?= e(canli_url((int) $r['id'])) ?>">Sınıfa gir</a></div>
    <?php endforeach; if (!$mine) echo '<p class="mt-3 text-muted">Açık oda yok.</p>'; ?>
    <a href="<?= e(url('ogretmen/canli.php')) ?>" class="btn-outline mt-4 text-sm">Yeni oda aç</a>
  </div>
  <div class="card p-5">
    <h2 class="font-display text-2xl">Diğer hocalar şu an</h2>
    <?php foreach ($others as $r): ?><p class="mt-3 text-sm"><span class="live-pill"><i></i> Canlı</span> <?= e($r['title']) ?> · <?= e($r['teacher_name']) ?></p><?php endforeach; ?>
  </div>
</div>
<?php panel_foot();