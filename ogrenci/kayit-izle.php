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
if (!$r) {
    redirect('ogrenci/kayitlar');
}
$backGid = (int) ($_GET['grup'] ?? $r['group_id'] ?? 0);
$back = $backGid > 0 ? 'ogrenci/kayitlar.php?grup=' . $backGid : 'ogrenci/kayitlar';
$src = '';
$vodJs = '';
if (function_exists('vod_player_src')) {
    [$vodJs, $src] = vod_player_src($r, (int) $u['id']);
} elseif (!empty($r['video_path']) && function_exists('vod_play_url')) {
    $vodJs = vod_play_url((int) $r['id'], (int) $u['id']);
} elseif (!empty($r['video_path'])) {
    $vodJs = url('api/dosya.php?tur=video&id=' . (int) $r['id']);
} elseif (!empty($r['video_url'])) {
    $src = (string) $r['video_url'];
}
panel_head('ogrenci', 'kayitlar', (string) $r['title'] . ' | Kayıt', $u);
?>
<div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1">
  <a class="text-sm font-extrabold text-navy" href="<?= e(url($back)) ?>">← <?= e($r['gname']) ?></a>
  <h2 class="font-display text-lg leading-tight"><?= e($r['title']) ?></h2>
  <span class="text-xs text-muted"><?= e($r['tname']) ?> · <?= e($r['recorded_on']) ?> · <?= (int) $r['mins'] ?> dk</span>
</div>
<style>.vod-wide{margin-left:-1.5rem;margin-right:-1.5rem;width:calc(100% + 3rem)}@media(min-width:768px){.vod-wide{margin-left:-2.25rem;margin-right:-2.25rem;width:calc(100% + 4.5rem)}}</style>
<div class="vod-wide" style="background:#000;border-radius:.75rem;overflow:hidden">
  <?php if ($vodJs === '' && $src === ''): ?>
    <p class="p-5 text-muted">Bu kayıt için henüz video yüklenmedi.</p>
  <?php elseif ($vodJs === '' && !empty($r['video_url']) && empty($r['video_path']) && !preg_match('/\.(mp4|webm|mov)(\?|$)/i', $src)): ?>
    <p class="p-5"><a class="btn-primary" href="<?= e($src) ?>" target="_blank" rel="noreferrer">Videoyu aç</a></p>
  <?php else: ?>
    <div id="vod-box" data-src="<?= e($vodJs !== '' ? $vodJs : $src) ?>" data-mins="<?= (int) $r['mins'] ?>">
      <video id="vod-player" class="block w-full bg-black" controls controlslist="nodownload noremoteplayback" disablepictureinpicture playsinline preload="metadata" oncontextmenu="return false"></video>
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
</div>
<?php panel_foot();
