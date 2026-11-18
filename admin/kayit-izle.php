<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    "SELECT rec.*, g.name gname, t.name tname
     FROM recordings rec
     JOIN class_groups g ON g.id = rec.group_id
     JOIN users t ON t.id = rec.teacher_id
     WHERE rec.id = ?"
);
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    flash_error('Kayıt bulunamadı.');
    redirect('admin/kayitlar');
}
if (post('delete_id')) {
    try {
        academy_delete_recording((int) post('delete_id'), (int) $u['id'], true);
        flash_ok('Kayıt silindi.');
        redirect('admin/kayitlar');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect('admin/kayit-izle.php?id=' . $id);
    }
}
if (function_exists('vod_prepare_recording')) {
    $r = vod_prepare_recording($r);
}
$test = function_exists('recording_is_test') && recording_is_test($r);
panel_head('admin', 'kayitlar', (string) $r['title'] . ' | Kayıt', $u);
$err = flash_error();
?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<div class="mb-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
  <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
    <a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/kayitlar')) ?>">← <?= e((string) $r['gname']) ?></a>
    <h2 class="font-display text-lg leading-tight"><?php if ($test): ?><span class="live-pill mr-2"><i></i> Test</span><?php endif; ?><?= e((string) $r['title']) ?></h2>
    <span class="text-xs text-muted"><?= e(function_exists('recording_card_meta') ? recording_card_meta($r) : ((string) $r['recorded_on'] . ' · ' . (int) $r['mins'] . ' dk')) ?></span>
  </div>
  <?= panel_delete_form('', ['delete_id' => (int) $r['id']], 'Bu ders kaydı silinsin mi?') ?>
</div>
<?= function_exists('vod_watch_html') ? vod_watch_html($r, (int) $u['id']) : '' ?>
<?php panel_foot();
