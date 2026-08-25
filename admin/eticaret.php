<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

$paidPay = (int) db()->query("SELECT COALESCE(SUM(total),0) FROM payments WHERE status='odendi'")->fetchColumn();
$legacy = (int) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE merchant_oid IS NULL OR merchant_oid=''")->fetchColumn();
$ciro = $paidPay + $legacy;

$ciroBugun = (int) db()->query("SELECT COALESCE(SUM(total),0) FROM payments WHERE status='odendi' AND DATE(COALESCE(paid_at, created_at)) = CURDATE()")->fetchColumn();
$ciroBugun += (int) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE (merchant_oid IS NULL OR merchant_oid='') AND DATE(created_at) = CURDATE()")->fetchColumn();

$ciroHafta = (int) db()->query("SELECT COALESCE(SUM(total),0) FROM payments WHERE status='odendi' AND COALESCE(paid_at, created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$ciroHafta += (int) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE (merchant_oid IS NULL OR merchant_oid='') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$nPay = (int) db()->query("SELECT COUNT(*) FROM payments WHERE status='odendi'")->fetchColumn();
$nOrd = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$nPaidOrd = (int) db()->query("SELECT COUNT(*) FROM orders WHERE pay_status='odendi' OR pay_status IS NULL OR pay_status=''")->fetchColumn();
$avgSepet = $nPaidOrd > 0
    ? (int) round((float) db()->query("SELECT COALESCE(AVG(total),0) FROM orders WHERE pay_status='odendi' OR pay_status IS NULL OR pay_status=''")->fetchColumn())
    : 0;

$low = (int) db()->query('SELECT COUNT(*) FROM books WHERE is_digital=0 AND stock<5')->fetchColumn();
$bekleyenKargo = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status IN ('Hazırlanıyor','Kargoda') AND ship_mode <> 'dijital'")->fetchColumn();

$lowBooks = db()->query('SELECT * FROM books WHERE is_digital=0 AND stock<5 ORDER BY stock ASC, id LIMIT 8')->fetchAll();
$sonSiparis = db()->query(
    "SELECT o.*, u.name, u.email,
            (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_n
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC LIMIT 10"
)->fetchAll();
$sonOdeme = db()->query(
    'SELECT p.*, u.name FROM payments p JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 10'
)->fetchAll();

panel_head('admin', 'eticaret', 'E-ticaret özeti | Admin', $u);
?>
<div class="dash-hello">
  <h2>Mağaza özeti</h2>
  <p>Ödenen ciro, kargo kuyruğu ve stok. Kitap siparişleri satın alınca burada görünür.</p>
</div>

<div class="grid gap-4 md:grid-cols-4">
  <a class="stat" href="<?= e(url('admin/siparisler')) ?>">
    <p class="stat-label">Ciro (ödendi)</p>
    <p class="stat-value"><?= money($ciro) ?></p>
    <p class="stat-hint"><?= $nOrd ?> sipariş · <?= $nPay ?> kart tahsilatı</p>
  </a>
  <div class="stat">
    <p class="stat-label">Bugün</p>
    <p class="stat-value"><?= money($ciroBugun) ?></p>
    <p class="stat-hint">Ödenen tahsilat</p>
  </div>
  <div class="stat">
    <p class="stat-label">Bu hafta</p>
    <p class="stat-value"><?= money($ciroHafta) ?></p>
    <p class="stat-hint">Son 7 gün</p>
  </div>
  <div class="stat">
    <p class="stat-label">Ortalama sepet</p>
    <p class="stat-value"><?= money($avgSepet) ?></p>
    <p class="stat-hint"><?= $nOrd ?> kitap siparişi</p>
  </div>
  <a class="stat" href="<?= e(url('admin/siparisler') . '?durum=' . rawurlencode('Hazırlanıyor')) ?>">
    <p class="stat-label">Bekleyen kargo</p>
    <p class="stat-value"><?= $bekleyenKargo ?></p>
    <p class="stat-hint">Hazırlanıyor / kargoda</p>
  </a>
  <a class="stat" href="<?= e(url('admin/urunler')) ?>">
    <p class="stat-label">Düşük stok</p>
    <p class="stat-value"><?= $low ?></p>
    <p class="stat-hint">Basılı, 5 altı</p>
  </a>
  <a class="stat" href="<?= e(url('admin/uyelikler')) ?>">
    <p class="stat-label">Ders üyelikleri</p>
    <p class="stat-value text-xl">Paketler</p>
    <p class="stat-hint">Yalnızca canlı ders</p>
  </a>
</div>

<div class="mt-6 flex flex-wrap gap-3">
  <a href="<?= e(url('admin/siparisler')) ?>" class="btn-primary text-sm">Siparişler</a>
  <a href="<?= e(url('admin/urunler')) ?>" class="btn-outline text-sm">Ürünler</a>
  <a href="<?= e(urun_yeni_url()) ?>" class="btn-outline text-sm">Yeni ürün</a>
  <a href="<?= e(url('admin/kategoriler')) ?>" class="btn-outline text-sm">Kategoriler</a>
  <a href="<?= e(url('admin/kampanyalar')) ?>" class="btn-outline text-sm">Kampanyalar</a>
  <a href="<?= e(url('admin/paytr')) ?>" class="btn-outline text-sm">Ödeme ayarları</a>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
  <section class="card overflow-hidden">
    <div class="flex items-center justify-between gap-3 border-b px-5 py-3">
      <p class="font-extrabold">Son siparişler</p>
      <a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/siparisler')) ?>">Tümü</a>
    </div>
    <?php if (!$sonSiparis): ?>
      <p class="dash-empty px-5 py-8">Henüz kitap siparişi yok. Ödeme tamamlanınca burada listelenir.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>No</th><th>Müşteri</th><th>Tutar</th><th>Durum</th></tr></thead>
        <tbody>
          <?php foreach ($sonSiparis as $o): ?>
            <tr>
              <td><a class="font-extrabold text-navy" href="<?= e(siparis_url((int) $o['id'])) ?>">#<?= (int) $o['id'] ?></a></td>
              <td>
                <a class="font-extrabold" href="<?= e(kullanici_url((int) $o['user_id'])) ?>"><?= e((string) $o['name']) ?></a>
                <span class="block text-xs text-muted"><?= (int) $o['item_n'] ?> kalem · <?= e(shop_date((string) $o['created_at'])) ?></span>
              </td>
              <td><?= money((int) $o['total']) ?></td>
              <td><span class="<?= shop_status_class((string) $o['status']) ?>"><?= e(shop_order_status_label((string) $o['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="card overflow-hidden">
    <div class="flex items-center justify-between gap-3 border-b px-5 py-3">
      <p class="font-extrabold">Son ödemeler</p>
      <a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/siparisler')) ?>">Tümü</a>
    </div>
    <?php if (!$sonOdeme): ?>
      <p class="dash-empty px-5 py-8">Henüz ödeme kaydı yok.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Kişi</th><th>Tür</th><th>Tutar</th><th>Durum</th></tr></thead>
        <tbody>
          <?php foreach ($sonOdeme as $p): ?>
            <tr>
              <td>
                <a class="font-extrabold" href="<?= e(kullanici_url((int) $p['user_id'])) ?>"><?= e((string) $p['name']) ?></a>
                <span class="block text-xs text-muted"><?= e(shop_date((string) $p['created_at'])) ?></span>
              </td>
              <td><?= e(payment_kind_label((string) $p['kind'])) ?></td>
              <td><?= money((int) $p['total']) ?></td>
              <td>
                <span class="<?= shop_status_class((string) $p['status']) ?>"><?= e(shop_pay_status_label((string) $p['status'])) ?></span>
                <?php if (!empty($p['order_id'])): ?>
                  <a class="ml-2 text-xs font-extrabold text-navy" href="<?= e(siparis_url((int) $p['order_id'])) ?>">Sipariş</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<section class="card mt-6 overflow-hidden">
  <div class="flex items-center justify-between gap-3 border-b px-5 py-3">
    <p class="font-extrabold">Düşük stok</p>
    <a class="text-sm font-extrabold text-navy" href="<?= e(url('admin/urunler')) ?>">Ürünler</a>
  </div>
  <?php if (!$lowBooks): ?>
    <p class="dash-empty px-5 py-8">Basılı ürünlerde 5 altı stok uyarısı yok.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Ürün</th><th>Stok</th><th>Fiyat</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($lowBooks as $b): ?>
          <tr class="is-low">
            <td>
              <span class="inline-flex items-center gap-3">
                <span class="prod-swatch"><?= book_cover_html($b, '', 'thumb') ?></span>
                <span class="font-extrabold"><?= e((string) $b['title']) ?></span>
              </span>
            </td>
            <td class="font-extrabold text-accent"><?= (int) $b['stock'] ?></td>
            <td><?= money((int) $b['price']) ?></td>
            <td><a class="font-extrabold text-navy" href="<?= e(urun_admin_url((int) $b['id'])) ?>">Düzenle</a>
              <span class="text-muted"> · </span>
              <a class="font-extrabold text-navy" href="<?= e(page_url('kitap', (string) $b['slug'])) ?>">Sitede gör</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php panel_foot();
