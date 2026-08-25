<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'live_host') {
    setting_set('live_host', preg_replace('/\s+/', '', post('live_host')));
    $saved = true;
}
$all = db()->query("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id ORDER BY r.status='live' DESC, r.id DESC")->fetchAll();
$detected = live_public_host();
panel_head('admin', 'canli', 'Canlı odalar | Admin', $u);
?>
<?php if ($saved): ?><p class="mb-4 font-bold text-green-700">Yayın host’u kaydedildi.</p><?php endif; ?>
<p class="mb-4 text-sm text-muted">Hocalar birbirini beklemez. Aynı dakikada birden fazla oda açık kalır. Öğrenciler yalnızca hocanın HLS yayınını izler. Planlı seanslar için <a class="font-extrabold text-navy" href="<?= e(url('admin/takvim')) ?>">ders takvimine</a> bakın.</p>
<form method="post" class="card mb-6 grid gap-3 p-5 sm:grid-cols-[1fr_auto] sm:items-end">
  <input type="hidden" name="action" value="live_host">
  <label class="text-sm font-bold">Yayın host’u (MediaMTX / OBS)
    <input name="live_host" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" placeholder="192.168.1.20 veya boş bırakın" value="<?= e(setting('live_host')) ?>">
    <span class="mt-1 block text-xs font-normal text-muted">Yalnızca OBS RTMP adresi içindir (şu an sayfa host’u: <?= e($detected) ?>). İzleme (HLS/WHEP) her zaman sayfanın açıldığı host’u kullanır — localhost ile 127.0.0.1 karışmasın. OBS aynı PC: <code>rtmp://127.0.0.1:1935/live</code> + odadaki anahtar. Telefondan izlemek için siteyi LAN IP ile açın.</span>
  </label>
  <button class="btn-primary">Kaydet</button>
</form>
<div class="card overflow-hidden"><table class="table"><thead><tr><th>Oda</th><th>Hoca</th><th>Durum</th><th></th></tr></thead><tbody>
<?php foreach ($all as $r): ?>
  <tr>
    <td><?= e($r['title']) ?> — <?= e($r['topic']) ?></td>
    <td><?= e($r['teacher_name']) ?></td>
    <td><?= $r['status'] === 'live' ? live_pill($r) : 'Bitti' ?></td>
    <td>
      <?php if ($r['status'] === 'live'): ?>
        <a class="font-extrabold text-accent" href="<?= e(canli_url((int) $r['id'])) ?>">İzle</a> ·
        <form class="inline" method="post" action="<?= e(url('api/live.php')) ?>"><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="goto" value="admin/canli.php"><button class="font-extrabold text-muted">Kapat</button></form>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php panel_foot();