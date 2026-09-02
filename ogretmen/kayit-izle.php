<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    "SELECT rec.*, g.name gname
     FROM recordings rec
     JOIN class_groups g ON g.id = rec.group_id
     WHERE rec.id = ? AND rec.teacher_id = ?"
);
$st->execute([$id, (int) $u['id']]);
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
[$vodJs, $src] = function_exists('vod_player_src')
    ? vod_player_src($r, (int) $u['id'])
    : ['', (string) ($r['video_url'] ?? '')];
if ($vodJs === '' && !empty($r['video_path'])) {
    $vodJs = url('api/dosya.php?tur=video&id=' . (int) $r['id']);
}
panel_head('ogretmen', 'kayitlar', (string) $r['title'] . ' | Kayıt', $u);
$err = flash_error();
?>
<?php if ($err): ?><p class="mb-4 font-bold text-accent"><?= e($err) ?></p><?php endif; ?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogretmen/kayit-yukle')) ?>">← Ders kayıtları</a></p>
<article class="card overflow-hidden p-5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <p class="text-xs font-extrabold uppercase text-navy"><?= e($r['gname']) ?></p>
      <h2 class="font-display mt-1 text-3xl"><?= e($r['title']) ?></h2>
      <p class="mt-1 text-sm text-muted"><?= e($r['recorded_on']) ?> · <?= (int) $r['mins'] ?> dk</p>
    </div>
    <?= panel_delete_form('', ['delete_id' => (int) $r['id']], 'Bu ders kaydı silinsin mi?') ?>
  </div>
  <?php if ($vodJs === '' && $src === ''): ?>
    <p class="mt-6 text-muted">Bu kayıt için henüz video yok.</p>
  <?php elseif ($vodJs === '' && !empty($r['video_url']) && empty($r['video_path']) && !preg_match('/\.(mp4|webm|mov)(\?|$)/i', $src)): ?>
    <p class="mt-6"><a class="btn-primary" href="<?= e($src) ?>" target="_blank" rel="noreferrer">Videoyu aç</a></p>
  <?php else: ?>
    <div id="vod-box" class="mt-6" data-src="<?= e($vodJs !== '' ? $vodJs : $src) ?>">
      <video id="vod-player" class="w-full rounded-2xl bg-black h-auto object-contain" controls controlslist="nodownload noremoteplayback" disablepictureinpicture playsinline preload="metadata" oncontextmenu="return false"></video>
    </div>
    <script>
    (function () {
      var box = document.getElementById('vod-box');
      var video = document.getElementById('vod-player');
      if (!box || !video) return;
      video.setAttribute('src', box.getAttribute('data-src') || '');
      video.addEventListener('contextmenu', function (e) { e.preventDefault(); });
      video.addEventListener('error', function () {
        if (box.querySelector('.vod-err')) return;
        var p = document.createElement('p');
        p.className = 'vod-err mt-3 font-bold text-accent';
        p.textContent = 'Video şu an açılamadı. Sayfayı yenileyin.';
        box.appendChild(p);
      });
    })();
    </script>
  <?php endif; ?>
</article>
<?php panel_foot();
