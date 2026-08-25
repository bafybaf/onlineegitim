<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/schedule.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$flash = schedule_handle_post($u, false);
schedule_sync_statuses();

$mode = ($_GET['gorunum'] ?? 'hafta') === 'ay' ? 'ay' : 'hafta';
$cursor = schedule_parse_day($_GET['t'] ?? null);
$groups = schedule_teacher_groups((int) $u['id']);
$editId = (int) ($_GET['duzenle'] ?? 0);
$edit = $editId ? schedule_by_id($editId) : null;
if ($edit && (int) $edit['teacher_id'] !== (int) $u['id']) {
    $edit = null;
}

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

$sessions = schedule_fetch($from, $to, ['teacher_id' => (int) $u['id']]);
$base = 'ogretmen/takvim';
panel_head('ogretmen', 'takvim', 'Takvim | Öğretmen Paneli', $u);
?>
<?php if ($flash): ?><p class="mb-4 font-bold text-green-700"><?= e($flash) ?></p><?php endif; ?>
<p class="mb-5 text-sm text-muted">Grup günleri yalnızca nottur. Buradan gerçek tarih ve saat yazın. Ders saati ±15 dakika içindeyse odayı mevcut canlı akışla açabilirsiniz.</p>
<?php schedule_toolbar($base, $mode, $cursor, $prev, $next, $title); ?>
<h3 class="mb-3 font-display text-xl"><?= $edit ? 'Dersi düzenle' : 'Yeni ders saati' ?></h3>
<?php if ($groups): ?>
  <?php schedule_form($groups, $edit, $edit ? 'Kaydet' : 'Saati ekle'); ?>
<?php else: ?>
  <p class="card p-5 text-muted">Size atanmış grup yok.</p>
<?php endif; ?>
<div class="mt-8">
<?php
if ($mode === 'ay') {
    schedule_render_month($sessions, $from, 'ogretmen', $base);
} else {
    schedule_render_week($sessions, $from, 'ogretmen', $base);
}
?>
</div>
<?php panel_foot();
