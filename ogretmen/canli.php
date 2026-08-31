<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogretmen');
$groups = db()->prepare('SELECT * FROM class_groups WHERE teacher_id=?');
$groups->execute([$u['id']]);
$groups = $groups->fetchAll();
$all = db()->query("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id WHERE r.status='live' ORDER BY r.id")->fetchAll();
panel_head('ogretmen', 'canli', 'Canlı odalar | Öğretmen Paneli', $u);
?>
<div class="grid gap-6 lg:grid-cols-2">
  <form method="post" action="<?= e(url('api/live.php')) ?>" class="card p-6">
    <h2 class="font-display text-2xl">Yeni canlı oda</h2>
    <p class="mt-2 text-sm text-muted">Açık odalar kapanmaz.<?= live_in_docker() ? '' : ' Yerelde start-live-server.bat çalışsın.' ?></p>
    <input type="hidden" name="action" value="start"><input type="hidden" name="html" value="1">
    <label class="mt-4 block text-sm font-bold">Grup
      <select name="group_id" class="mt-1 w-full rounded-xl border px-3 py-2"><?php foreach ($groups as $g): ?><option value="<?= (int) $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select>
    </label>
    <label class="mt-3 block text-sm font-bold">Konu<input name="topic" class="mt-1 w-full rounded-xl border px-3 py-2" value="Yeni ders"></label>
    <input type="hidden" name="record" value="1">
    <label class="mt-2 flex items-center gap-2 text-sm font-bold"><input type="checkbox" name="yoklama" value="1" checked> Yoklamayı aç</label>
    <button class="btn-primary mt-5">Odayı aç (diğerleri devam eder)</button>
  </form>
  <div>
    <h2 class="font-display text-2xl">Sistemdeki tüm canlı odalar</h2>
    <?php foreach ($all as $r): ?>
      <div class="card mt-3 p-4"><?= live_pill($r) ?>
        <p class="mt-2 font-extrabold"><?= e($r['title']) ?> — <?= e($r['topic']) ?></p>
        <p class="text-sm text-muted"><?= e($r['teacher_name']) ?><?= (int) $r['teacher_id'] === (int) $u['id'] ? ' · sizin odanız' : '' ?></p>
        <?php if ((int) $r['teacher_id'] === (int) $u['id']): ?>
          <div class="mt-3 flex gap-2"><a class="btn-primary text-sm" href="<?= e(canli_url((int) $r['id'])) ?>">Gir</a>
          <form method="post" action="<?= e(url('api/live.php')) ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="goto" value="ogretmen/kayit-yukle.php"><button class="btn-outline text-sm">Bitir</button></form></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php panel_foot();