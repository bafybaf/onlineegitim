<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$mine = live_student_live_rooms((int) $u['id']);
$ids = array_column($mine, 'id') ?: [0];
$others = db()->query("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id WHERE r.status='live' AND r.id NOT IN (" . implode(',', array_map('intval', $ids)) . ")")->fetchAll();
$others = array_values(array_filter($others, static fn(array $r): bool => !live_room_is_test($r)));
panel_head('ogrenci', 'canli', 'Canlı dersler | Öğrenci Paneli', $u);
membership_panel_banner($u);
?>
<p class="mb-4 text-sm text-muted">Aynı anda birden çok oda açık kalır. Sadece kayıtlı olduğunuz gruplara girebilirsiniz. Kontenjan dolunca kayıtlardan izlersiniz.</p>
<h2 class="font-display text-2xl">Sizin açık odalarınız</h2>
<div class="mt-3 grid gap-3">
<?php foreach ($mine as $r):
    $reason = live_student_watch_reason($r, (int) $u['id']);
    $open = $reason === null;
    ?>
  <div class="card flex flex-wrap items-center justify-between gap-3 p-5"><?= live_pill($r) ?><div><p class="font-extrabold"><?= e($r['title']) ?> — <?= e($r['topic']) ?></p><p class="text-sm text-muted"><?= e($r['teacher_name']) ?></p>
  <?php if (!$open): ?><p class="mt-1 text-sm font-bold text-accent"><?= e($reason) ?></p><?php endif; ?>
  </div>
  <?php if ($open): ?>
  <a class="btn-primary" href="<?= e(canli_url((int) $r['id'])) ?>">Gir</a>
  <?php else: ?>
  <a class="btn-outline" href="<?= e(live_watch_recordings_url((int) $r['group_id'])) ?>">Kayıttan izle</a>
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