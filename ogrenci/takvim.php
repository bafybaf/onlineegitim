<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/schedule.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
schedule_sync_statuses();

$mode = ($_GET['gorunum'] ?? 'hafta') === 'ay' ? 'ay' : 'hafta';
$cursor = schedule_parse_day($_GET['t'] ?? null);
$gids = schedule_student_group_ids((int) $u['id']);

if ($mode === 'ay') {
    $from = schedule_month_first($cursor);
    $to = $from->modify('first day of next month');
    $prev = $from->modify('-1 month');
    $next = $to;
    $title = schedule_ay_adi((int) $from->format('n')) . ' ' . $from->format('Y');
} else {
    $from = schedule_week_monday($cursor);
    $to = $from->modify('+7 days');
    $prev = $from->modify('-7 days');
    $next = $to;
    $title = $from->format('j') . '–' . $from->modify('+6 days')->format('j') . ' ' . schedule_ay_adi((int) $from->format('n')) . ' ' . $from->format('Y');
}

$sessions = $gids ? schedule_fetch($from, $to, ['group_ids' => $gids]) : [];
$upcoming = $gids ? schedule_fetch(schedule_now()->modify('-30 minutes'), schedule_now()->modify('+14 days'), ['group_ids' => $gids]) : [];
$upcoming = array_values(array_filter($upcoming, static fn(array $r): bool => ($r['display_status'] ?? '') !== 'iptal' && ($r['display_status'] ?? '') !== 'bitti'));
$base = 'ogrenci/takvim';
panel_head('ogrenci', 'takvim', 'Takvim | Öğrenci Paneli', $u);
?>
<p class="mb-5 text-sm text-muted">Yalnızca kayıtlı olduğunuz grupların planlı canlı dersleri. Oda açıldığında <b>Katıl</b> görünür.</p>
<?php schedule_toolbar($base, $mode, $cursor, $prev, $next, $title); ?>

<h3 class="font-display text-xl">Yaklaşan dersler</h3>
<div class="mt-3 grid gap-3">
<?php if (!$upcoming): ?>
  <p class="card p-5 text-muted"><?= $gids ? 'Önümüzdeki iki haftada planlı ders yok.' : 'Kayıtlı grubunuz yok.' ?></p>
<?php endif; ?>
<?php foreach (array_slice($upcoming, 0, 8) as $row):
    $start = schedule_parse_datetime((string) $row['starts_at']);
    $when = $start ? schedule_gun_kisa((int) $start->format('N')) . ' ' . $start->format('j') . ' ' . schedule_ay_adi((int) $start->format('n')) . ' · ' . $start->format('H:i') : '';
    ?>
  <div class="card flex flex-wrap items-center justify-between gap-3 p-5">
    <div>
      <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-muted"><?= e($when) ?> · <?= (int) $row['duration_min'] ?> dk</p>
      <p class="font-extrabold"><?= e((string) $row['title']) ?><?= $row['topic'] ? ' - ' . e((string) $row['topic']) : '' ?></p>
      <p class="text-sm text-muted"><?= e((string) $row['group_name']) ?> · <?= e((string) $row['teacher_name']) ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?= schedule_badge((string) $row['display_status']) ?>
      <?= schedule_actions($row, 'ogrenci', $base) ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="mt-8">
<?php
if ($mode === 'ay') {
    schedule_render_month($sessions, $from, 'ogrenci', $base);
} else {
    schedule_render_week($sessions, $from, 'ogrenci', $base);
}
?>
</div>
<?php panel_foot();
