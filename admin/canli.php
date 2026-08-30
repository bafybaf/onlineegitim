<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$all = db()->query("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id ORDER BY r.status='live' DESC, r.id DESC")->fetchAll();
panel_head('admin', 'canli', 'Canlı odalar | Admin', $u);
?>
<p class="mb-4 text-sm text-muted">Hocalar sınıfta tarayıcı kamerasını açar; öğrenciler aynı siteden izler. Aynı anda birden fazla oda açık kalır. Planlı seanslar için <a class="font-extrabold text-navy" href="<?= e(url('admin/takvim')) ?>">ders takvimine</a> bakın.</p>
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