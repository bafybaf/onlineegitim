<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$id = (int) ($_GET['id'] ?? 0);
$owned = function_exists('group_owned_in_sql') ? group_owned_in_sql((int) $u['id']) : '0';
$st = db()->prepare(
    "SELECT rec.*, g.name gname, t.name tname
     FROM recordings rec
     JOIN class_groups g ON g.id = rec.group_id
     JOIN users t ON t.id = rec.teacher_id
     WHERE rec.id = ? AND rec.group_id IN ($owned)"
);
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    flash_error('Kayıt bulunamadı.');
    redirect('ogretmen/kayit-yukle');
}
if (post('delete_id')) {
    try {
        academy_delete_recording((int) post('delete_id'), (int) $u['id']);
        flash_ok('Kayıt silindi.');
        redirect('ogretmen/kayit-yukle');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect('ogretmen/kayit-izle.php?id=' . $id);
    }
}
if (function_exists('vod_prepare_recording')) {
    $r = vod_prepare_recording($r);
}
panel_head('ogretmen', 'kayitlar', (string) $r['title'] . ' | Kayıt', $u);
$err = flash_error();
?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
  <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
    <a class="text-sm font-extrabold text-navy" href="<?= e(url('ogretmen/kayit-yukle')) ?>">← <?= e($r['gname']) ?></a>
    <h2 class="font-display text-lg leading-tight"><?= e($r['title']) ?></h2>
    <span class="text-xs text-muted"><?= e(function_exists('recording_card_meta') ? recording_card_meta($r) : ((string) $r['recorded_on'] . ' · ' . (int) $r['mins'] . ' dk')) ?></span>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <a class="btn-outline text-sm" href="<?= e(url('ogretmen/kayit-yukle.php' . '?grup=' . (int) ($r['group_id'] ?? 0) . '&duzenle=' . (int) $r['id'] . '#kayit-' . (int) $r['id'])) ?>">Düzenle</a>
    <?= panel_delete_form('', ['delete_id' => (int) $r['id']], 'Bu ders kaydı silinsin mi?') ?>
  </div>
</div>
<?= function_exists('vod_watch_html') ? vod_watch_html($r, (int) $u['id']) : '' ?>
<?php panel_foot();
