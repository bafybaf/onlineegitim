<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');
$nStu = (int) db()->query("SELECT COUNT(*) FROM users WHERE role='ogrenci'")->fetchColumn();
$nTeach = (int) db()->query("SELECT COUNT(*) FROM users WHERE role='ogretmen'")->fetchColumn();
$nLive = (int) db()->query("SELECT COUNT(*) FROM live_rooms WHERE status='live'")->fetchColumn();
$nOrd = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$live = db()->query("SELECT r.*, t.name teacher_name FROM live_rooms r JOIN users t ON t.id=r.teacher_id WHERE r.status='live'")->fetchAll();
panel_head('admin', 'dashboard', 'Yönetim özeti | Admin', $u);
?>
<div class="grid gap-4 md:grid-cols-4">
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Öğrenci</p><p class="font-display mt-1 text-2xl"><?= $nStu ?></p></div>
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Hoca</p><p class="font-display mt-1 text-2xl"><?= $nTeach ?></p></div>
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Eşzamanlı oda</p><p class="font-display mt-1 text-2xl"><?= $nLive ?></p></div>
  <div class="stat"><p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Sipariş</p><p class="font-display mt-1 text-2xl"><?= $nOrd ?></p></div>
</div>
<div class="mt-6 grid gap-4 md:grid-cols-2">
  <a class="card p-5 hover:border-navy" href="<?= e(url('admin/anasayfa')) ?>">
    <p class="stat-label">Site</p>
    <h2 class="font-display mt-1 text-2xl">Anasayfa hero ve şerit</h2>
    <p class="mt-2 text-sm text-muted">Slayt ekle · düzenle · sil</p>
  </a>
  <a class="card p-5 hover:border-navy" href="<?= e(url('admin/programlar')) ?>">
    <p class="stat-label">Eğitim</p>
    <h2 class="font-display mt-1 text-2xl">Eğitimler, gruplar, üyelikler</h2>
    <p class="mt-2 text-sm text-muted">Eğitimler · Gruplar · Üyelikler · Canlı · Takvim</p>
  </a>
  <a class="card p-5 hover:border-navy" href="<?= e(url('admin/eticaret')) ?>">
    <p class="stat-label">Mağaza</p>
    <h2 class="font-display mt-1 text-2xl">E-ticaret, sipariş, ürün</h2>
    <p class="mt-2 text-sm text-muted">E-ticaret özeti · Siparişler · Ürünler</p>
  </a>
</div>
<div class="card mt-6 p-5"><h2 class="font-display text-2xl">Şu an canlı</h2>
<?php foreach ($live as $r): ?><p class="mt-3"><?= live_pill($r) ?> <b><?= e($r['title']) ?></b> · <?= e($r['teacher_name']) ?> · <a class="font-extrabold text-navy" href="<?= e(canli_url((int) $r['id'])) ?>">İzle</a></p><?php endforeach; ?>
</div>
<p class="mt-6 text-sm"><a class="font-extrabold text-navy" href="<?= e(url('admin/paytr.php')) ?>">Ödeme ayarları →</a>
  PayTR: <?= paytr_configured() ? 'kayıtlı' . (setting_bool('paytr_test_mode', true) ? ' · test' : ' · canlı') : 'yok' ?>
  · iyzico: <?= function_exists('iyzico_configured') && iyzico_configured() ? (setting_bool('iyzico_sandbox', true) ? 'açık · sandbox' : 'açık · canlı') : 'kapalı' ?>
</p>
<?php panel_foot();