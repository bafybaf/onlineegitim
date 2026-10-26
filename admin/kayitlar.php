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
$tid = (int) ($_GET['hoca'] ?? 0);
$err = flash_error();
$ok = flash_ok();
if (post('action') === 'restore_manual_cf' && function_exists('vod_restore_manual_recordings_from_cf')) {
    try {
        $rs = vod_restore_manual_recordings_from_cf(db(), 3);
        $done = (int) ($rs['done'] ?? 0);
        $left = (int) ($rs['left'] ?? 0);
        if ($done > 0 && $left > 0) {
            flash_ok($done . ' manuel kayıt sunucuya alındı. Kalan ' . $left . '; tekrar basın.');
        } elseif ($done > 0) {
            flash_ok($done . ' manuel kayıt sunucuya alındı. Cloudflare kopyası silindi.');
        } elseif ($left > 0) {
            flash_error('İndirme henüz hazır değil veya kota doldu. Bir dakika sonra tekrar basın. Kalan: ' . $left);
        } else {
            flash_ok('Sunucuya alınacak manuel Cloudflare kaydı kalmadı.');
        }
    } catch (Throwable $e) {
        flash_error('Geri alma çalışmadı. Sayfayı yenileyip tekrar deneyin.');
    }
    redirect($tid ? ('admin/kayitlar.php?hoca=' . $tid) : 'admin/kayitlar');
}
if (post('delete_id')) {
    try {
        academy_delete_recording((int) post('delete_id'), (int) $u['id'], true);
        flash_ok('Kayıt silindi.');
        redirect($tid ? ('admin/kayitlar.php?hoca=' . $tid) : 'admin/kayitlar');
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
         ORDER BY rec.recorded_on ASC, rec.id ASC
         LIMIT 300'
    )->fetchAll();
} catch (Throwable) {
    $rows = [];
}
[$rows, $teachers] = recordings_by_teacher_recent($rows);
if ($tid && !isset($teachers[$tid])) {
    $tid = 0;
}
if ($tid) {
    $rows = array_values(array_filter($rows, static fn (array $r): bool => (int) $r['teacher_id'] === $tid));
}
panel_head('admin', 'kayitlar', 'Ders kayıtları | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<p class="mb-4 text-sm text-muted">Kayıtlar yükleyen hocaya göre ayrılır. Test kayıtları öğrenci listesine düşmez. Manuel yüklenenler sunucuda kalır; yalnız canlı ders kaydı Cloudflare’a gider.</p>
<?php
$manualCfN = 0;
if (function_exists('vod_manual_cf_recordings')) {
    $manualCfN = count(vod_manual_cf_recordings(db()));
}
if ($manualCfN > 0):
?>
  <form method="post" class="card mb-5 p-5">
    <input type="hidden" name="action" value="restore_manual_cf">
    <p class="font-extrabold">Manuel kayıtlar Cloudflare’da</p>
    <p class="mt-1 text-sm text-muted"><?= (int) $manualCfN ?> video sunucudan silinmiş olabilir. Geri alınca dosya tekrar storage’a iner, Cloudflare kopyası silinir. Her tıkta birkaç tane gelir.</p>
    <button class="btn-primary mt-3 h-10 w-fit text-sm">Manuel kayıtları sunucuya al</button>
  </form>
<?php endif; ?>
<?php if (count($teachers) > 1): ?>
  <div class="mb-5 flex flex-wrap gap-2">
    <a class="<?= $tid === 0 ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('admin/kayitlar')) ?>">Tüm hocalar</a>
    <?php foreach ($teachers as $hid => $hname): ?>
      <a class="<?= $tid === (int) $hid ? 'btn-primary' : 'btn-outline' ?> text-sm" href="<?= e(url('admin/kayitlar.php?hoca=' . (int) $hid)) ?>"><?= e($hname) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php
$last = null;
foreach ($rows as $r):
    $ready = !empty($r['video_path']) || !empty($r['video_url']);
    $watch = url('admin/kayit-izle.php?id=' . (int) $r['id']);
    $test = function_exists('recording_is_test') && recording_is_test($r);
    $hid = (int) $r['teacher_id'];
    if ($tid === 0 && $hid !== $last):
        $last = $hid;
        ?>
  <div class="mb-3 <?= $last === (int) $rows[0]['teacher_id'] ? 'mt-0' : 'mt-8' ?>">
    <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-navy">Hoca</p>
    <h2 class="font-display text-2xl"><?= e((string) $r['tname']) ?></h2>
  </div>
        <?php
    endif;
    ?>
  <article class="card mb-3 flex flex-wrap items-start justify-between gap-3 p-5">
    <div>
    <p class="font-extrabold"><?php if ($test): ?><span class="live-pill mr-2"><i></i> Test</span><?php endif; ?><?= e((string) $r['title']) ?></p>
    <p class="text-sm text-muted"><?= e((string) $r['gname']) ?> · <?= e((string) $r['recorded_on']) ?> · <?= e(function_exists('vod_length_label') ? vod_length_label($r) : ((int) $r['mins'] . ' dk')) ?></p>
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
<?php if (!$rows): ?><p class="text-muted"><?= $tid ? 'Bu hocaya ait kayıt yok.' : 'Henüz kayıt yok. Test yayınında “Kayıt”a basıp odayı bitirince video burada görünür.' ?></p><?php endif; ?>
<?php panel_foot();
