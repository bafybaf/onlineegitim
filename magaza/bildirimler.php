<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('musteri');
if (isset($_GET['oku'])) {
    db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([(int) $u['id']]);
    redirect('magaza/bildirimler');
}
$rows = db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');
$rows->execute([(int) $u['id']]);
$rows = $rows->fetchAll();
panel_head('musteri', 'bildirimler', 'Bildirimler | Mağaza', $u);
?>
<p class="mb-4 text-sm text-muted">Sipariş ve kargo güncellemeleri burada görünür.</p>
<p class="mb-4"><a class="text-sm font-extrabold text-navy" href="<?= e(url('magaza/bildirimler.php?oku=1')) ?>">Tümünü okundu işaretle</a></p>
<?php if (!$rows): ?><div class="card p-5">Bildirim yok.</div><?php endif; ?>
<?php foreach ($rows as $n): ?>
  <article class="card mb-3 p-5 <?= $n['read_at'] ? '' : 'border-navy' ?>">
    <p class="font-extrabold"><?= e((string) $n['title']) ?></p>
    <p class="text-sm text-muted"><?= e((string) $n['body']) ?> · <?= e(shop_date((string) $n['created_at'])) ?></p>
    <?php if (!empty($n['link'])): ?><a class="mt-2 inline-block text-sm font-extrabold text-navy" href="<?= e((string) $n['link']) ?>">Aç →</a><?php endif; ?>
  </article>
<?php endforeach; ?>
<?php panel_foot();
