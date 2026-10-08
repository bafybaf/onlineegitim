<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
if (function_exists('ensure_recordings_test_schema')) {
    ensure_recordings_test_schema();
}
if (function_exists('vod_recover_pending_rooms')) {
    try {
        vod_recover_pending_rooms(db());
    } catch (Throwable $e) {
    }
}
$err = flash_error();
$ok = flash_ok();
if (post('delete_id')) {
    try {
        academy_delete_recording((int) post('delete_id'), (int) $u['id'], true);
        flash_ok('Kayıt silindi.');
        redirect('admin/kayitlar');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
$rows = [];
try {
    $rows = db()->query(
        'SELECT rec.*, g.name gname, t.name tname
         FROM recordings rec
         JOIN class_groups g ON g.id = rec.group_id
         JOIN users t ON t.id = rec.teacher_id
         ORDER BY rec.id DESC
         LIMIT 300'
    )->fetchAll();
} catch (Throwable) {
    $rows = [];
}
panel_head('admin', 'kayitlar', 'Ders kayıtları | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<p class="mb-4 text-sm text-muted">Canlı ders ve test yayınında alınan videolar burada. Test kayıtları öğrenci listesine düşmez.</p>
<?php foreach ($rows as $r):
    $ready = !empty($r['video_path']) || !empty($r['video_url']);
    $watch = url('admin/kayit-izle.php?id=' . (int) $r['id']);
    $test = function_exists('recording_is_test') && recording_is_test($r);
    ?>
  <article class="card mb-3 flex flex-wrap items-start justify-between gap-3 p-5">
    <div>
    <p class="font-extrabold"><?php if ($test): ?><span class="live-pill mr-2"><i></i> Test</span><?php endif; ?><?= e((string) $r['title']) ?></p>
    <p class="text-sm text-muted"><?= e((string) $r['gname']) ?> · <?= e((string) $r['tname']) ?> · <?= e((string) $r['recorded_on']) ?> · <?= e(function_exists('vod_length_label') ? vod_length_label($r) : ((int) $r['mins'] . ' dk')) ?></p>
    <p class="mt-1 text-xs text-muted"><?= $ready ? 'Video hazır' : 'Video yok' ?></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?php if ($ready): ?>
        <a class="btn-primary text-sm" href="<?= e($watch) ?>">İzle</a>
      <?php endif; ?>
      <?= panel_delete_form('', ['delete_id' => (int) $r['id']], 'Bu ders kaydı silinsin mi?') ?>
    </div>
  </article>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="text-muted">Henüz kayıt yok. Test yayınında “Kayıt”a basıp odayı bitirince video burada görünür.</p><?php endif; ?>
<?php panel_foot();
