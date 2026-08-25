<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$ok = flash_ok();
$err = flash_error();
$hocalar = db()->query("SELECT * FROM users WHERE role='ogretmen' ORDER BY " . catalog_order_sql('', 'users'))->fetchAll();
$groupN = [];
$liveN = [];
$liveRooms = [];
if ($hocalar) {
    $ids = array_map(static fn(array $t): int => (int) $t['id'], $hocalar);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT teacher_id, COUNT(*) n FROM class_groups WHERE teacher_id IN ($in) GROUP BY teacher_id");
    $st->execute($ids);
    foreach ($st as $row) {
        $groupN[(int) $row['teacher_id']] = (int) $row['n'];
    }
    $st = db()->prepare("SELECT id, teacher_id, title, started_at FROM live_rooms WHERE teacher_id IN ($in) AND status='live'");
    $st->execute($ids);
    foreach ($st as $row) {
        $tid = (int) $row['teacher_id'];
        $liveRooms[$tid][] = $row;
        $liveN[$tid] = ($liveN[$tid] ?? 0) + 1;
    }
}
panel_head('admin', 'hocalar', 'Hocalar | Admin', $u);
?>
<div class="mb-4 flex flex-wrap items-start justify-between gap-3">
  <p class="text-sm text-muted">Kadro hocaları. Kartın tutamacını sürükleyerek kadro sırasını değiştirin.</p>
  <a class="btn-primary text-sm" href="<?= e(hoca_admin_url(0)) ?>">Yeni hoca</a>
</div>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<?php if (!$hocalar): ?>
  <p class="dash-empty">Henüz hoca yok. <a class="font-extrabold text-navy" href="<?= e(hoca_admin_url(0)) ?>">İlk hocayı ekleyin</a>.</p>
<?php else: ?>
<div class="grid gap-4 md:grid-cols-2" data-sort-table="users">
  <?php foreach ($hocalar as $t):
      $tid = (int) $t['id'];
      $g = $groupN[$tid] ?? 0;
      $l = $liveN[$tid] ?? 0;
      ?>
    <article class="card p-5" data-sort-id="<?= $tid ?>">
      <div class="flex items-start gap-3">
        <span class="sort-handle mt-1" title="Sürükleyerek sıralayın">⋮⋮</span>
        <?= user_avatar_html($t, 'sm') ?>
        <div class="min-w-0">
          <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy"><?= e((string) ($t['bio'] ?: ($t['city'] ?: 'Hoca'))) ?></p>
          <h2 class="font-display mt-1 text-2xl"><?= e((string) $t['name']) ?></h2>
          <p class="mt-1 text-sm text-muted"><?= e((string) $t['email']) ?></p>
        </div>
      </div>
      <p class="mt-3 text-sm text-muted"><?= $g ?> grup · <?= $l ?> açık oda · <?= e(user_status_label((string) $t['status'])) ?></p>
      <?php
      $lives = $liveRooms[$tid] ?? [];
      foreach ($lives as $r):
          ?>
        <p class="mt-2"><?= live_pill($r + ['status' => 'live']) ?> <?= e((string) $r['title']) ?>
          <a class="font-extrabold text-navy" href="<?= e(canli_url((int) $r['id'])) ?>">İzle</a></p>
      <?php endforeach; ?>
      <p class="mt-4 flex flex-wrap items-center gap-3">
        <a class="btn-primary text-sm" href="<?= e(hoca_admin_url($tid)) ?>">Düzenle</a>
        <?php if (!empty($t['slug'])): ?>
          <a class="btn-outline text-sm" href="<?= e(page_url('hoca', (string) $t['slug'])) ?>" target="_blank" rel="noreferrer">Kadro</a>
        <?php endif; ?>
        <?= panel_delete_form(hoca_admin_url($tid), ['act' => 'sil'], 'Hoca silinsin mi? Gruplara bağlıysa silinmez.') ?>
      </p>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php panel_foot();
