<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
ensure_live_attendance_schema();
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    'SELECT r.*, t.name teacher_name, g.name gname, g.cap
     FROM live_rooms r
     JOIN users t ON t.id = r.teacher_id
     JOIN class_groups g ON g.id = r.group_id
     WHERE r.id = ?'
);
$st->execute([$id]);
$room = $st->fetch();
if (!$room) {
    redirect('admin/canli');
}
$cap = max(1, (int) $room['cap']);
$entered = live_entered_students($id);
$presentN = 0;
foreach ($entered as $s) {
    if ((int) $s['present'] === 1) {
        $presentN++;
    }
}
$live = ($room['status'] ?? '') === 'live';
$startTs = strtotime((string) ($room['started_at'] ?? '')) ?: 0;
$endTs = $live ? time() : (strtotime((string) ($room['ended_at'] ?? '')) ?: $startTs);
$mins = $startTs ? max(1, (int) ceil(max(0, $endTs - $startTs) / 60)) : live_mins((string) ($room['started_at'] ?? ''));
panel_head('admin', 'canli', 'Canlı ders · ' . $room['title'] . ' | Admin', $u);
?>
<p class="mb-4 text-sm"><a class="font-extrabold text-navy" href="<?= e(url('admin/canli')) ?>">← Canlı dersler</a></p>
<div class="card mb-5 p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <p class="stat-label">Canlı ders</p>
      <h1 class="font-display mt-1 text-2xl"><?= e($room['title']) ?></h1>
      <p class="mt-1 text-sm text-muted"><?= e((string) $room['gname']) ?> · <?= e($room['teacher_name']) ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?= $live ? live_pill($room) : '<span class="text-sm font-extrabold text-muted">Bitti</span>' ?>
      <?php if ($live): ?>
        <a class="btn-primary text-sm" href="<?= e(canli_url($id)) ?>">Gözlemle</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="stat">
      <p class="stat-label">Açılış</p>
      <p class="stat-value text-lg"><?= e(profile_dt((string) ($room['started_at'] ?? ''))) ?></p>
    </div>
    <div class="stat">
      <p class="stat-label"><?= $live ? 'Süre (şimdi)' : 'Bitiş' ?></p>
      <p class="stat-value text-lg"><?= $live ? ((int) $mins . ' dk') : e(profile_dt((string) ($room['ended_at'] ?? ''))) ?></p>
    </div>
    <div class="stat">
      <p class="stat-label">Kontenjan</p>
      <p class="stat-value text-lg"><?= $presentN ?> / <?= $cap ?></p>
    </div>
    <div class="stat">
      <p class="stat-label">Girebilen</p>
      <p class="stat-value text-lg"><?= count($entered) ?> öğrenci</p>
    </div>
  </div>
</div>
<div class="card overflow-hidden">
  <div class="px-5 py-4">
    <p class="stat-label">Yer kapısı</p>
    <h2 class="font-display mt-1 text-xl">İlk <?= $cap ?> kişi girebildi</h2>
    <p class="mt-1 text-sm text-muted">Sıra, derse ilk giren öğrenciye göredir. Kontenjan dolunca sonrakiler kayıtlara yönlenir.</p>
  </div>
  <?php if (!$entered): ?>
    <p class="dash-empty px-5 pb-5">Bu derse henüz öğrenci girmedi.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Öğrenci</th>
          <th>E-posta</th>
          <th>Giriş saati</th>
          <th>Durum</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ($entered as $s): $i++; ?>
          <tr>
            <td><?= $i ?></td>
            <td class="font-extrabold"><?= e($s['name']) ?></td>
            <td class="text-sm text-muted"><?= e((string) ($s['email'] ?? '')) ?></td>
            <td><?= e(profile_dt((string) ($s['entered_at'] ?? ''))) ?></td>
            <td><?= ((int) $s['present'] === 1) ? '<span class="mem-ok font-extrabold">Derste</span>' : '<span class="text-muted">Çıktı</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php panel_foot();
