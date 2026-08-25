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
$src = '';
if (!empty($r['video_path'])) {
    $src = url('api/dosya.php?tur=video&id=' . (int) $r['id']);
} elseif (!empty($r['video_url'])) {
    $src = (string) $r['video_url'];
}
panel_head('ogrenci', 'kayitlar', (string) $r['title'] . ' | Kayıt', $u);
?>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('ogrenci/kayitlar')) ?>">← Ders kayıtları</a></p>
<article class="card overflow-hidden p-5">
  <p class="text-xs font-extrabold uppercase text-navy"><?= e($r['gname']) ?> · <?= e($r['tname']) ?></p>
  <h2 class="font-display mt-1 text-3xl"><?= e($r['title']) ?></h2>
  <p class="mt-1 text-sm text-muted"><?= e($r['recorded_on']) ?> · <?= (int) $r['mins'] ?> dk</p>
  <?php if ($src === ''): ?>
    <p class="mt-6 text-muted">Bu kayıt için henüz video yüklenmedi.</p>
  <?php elseif (!empty($r['video_url']) && empty($r['video_path']) && !preg_match('/\.(mp4|webm|mov)(\?|$)/i', $src)): ?>
    <p class="mt-6"><a class="btn-primary" href="<?= e($src) ?>" target="_blank" rel="noreferrer">Videoyu aç</a></p>
  <?php else: ?>
    <video class="mt-6 w-full rounded-2xl bg-black" controls preload="metadata" src="<?= e($src) ?>"></video>
  <?php endif; ?>
</article>
<?php panel_foot();
