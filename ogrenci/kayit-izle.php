<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    "SELECT rec.*, g.name gname, t.name tname
     FROM recordings rec
     JOIN class_groups g ON g.id=rec.group_id
     JOIN users t ON t.id=rec.teacher_id
     JOIN enrollments e ON e.group_id=rec.group_id AND e.student_id=?
     WHERE rec.id=?"
);
$st->execute([(int) $u['id'], $id]);
$r = $st->fetch();
if (!$r || (function_exists('recording_is_test') && recording_is_test($r))) {
    redirect('ogrenci/kayitlar');
}
if (function_exists('vod_prepare_recording')) {
    $r = vod_prepare_recording($r);
}
$backGid = (int) ($_GET['grup'] ?? $r['group_id'] ?? 0);
$back = $backGid > 0 ? 'ogrenci/kayitlar.php?grup=' . $backGid : 'ogrenci/kayitlar';
panel_head('ogrenci', 'kayitlar', (string) $r['title'] . ' | Kayıt', $u);
?>
<div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1">
  <a class="text-sm font-extrabold text-navy" href="<?= e(url($back)) ?>">← <?= e($r['gname']) ?></a>
  <h2 class="font-display text-lg leading-tight"><?= e($r['title']) ?></h2>
  <span class="text-xs text-muted"><?= e(function_exists('recording_card_meta') ? recording_card_meta($r) : ((string) $r['recorded_on'] . ' · ' . (int) $r['mins'] . ' dk')) ?></span>
</div>
<?= function_exists('vod_watch_html') ? vod_watch_html($r, (int) $u['id']) : '' ?>
<?php panel_foot();
