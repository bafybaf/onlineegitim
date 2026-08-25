<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('admin');

if (post('id') && post('act') === 'ilerlet') {
    $oid = (int) post('id');
    $st = db()->prepare(
        'SELECT o.*, u.email, u.role, u.name FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id=?'
    );
    $st->execute([$oid]);
    $cur = $st->fetch();
    if ($cur) {
        $res = shop_order_advance($cur, (int) $u['id']);
        if ($res['changed']) {
            flash_ok($res['mailed']
                ? 'Sipariş durumu güncellendi. Müşteriye e-posta gitti.'
                : 'Sipariş durumu güncellendi. Müşteriye panel bildirimi gitti.');
        }
    }
    redirect('admin/siparisler');
}

$durumlar = ['Hazırlanıyor', 'Kargoda', 'Teslim edildi', 'Dijital teslim'];
$durum = trim((string) ($_GET['durum'] ?? ''));
$tarih = trim((string) ($_GET['tarih'] ?? ''));
$bas = trim((string) ($_GET['bas'] ?? ''));
$bit = trim((string) ($_GET['bit'] ?? ''));
if (!in_array($durum, $durumlar, true)) {
    $durum = '';
}
if (!in_array($tarih, ['bugun', 'hafta', 'ay'], true)) {
    $tarih = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bas)) {
    $bas = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bit)) {
    $bit = '';
}

$sql = "SELECT o.*, u.name, u.email,
               (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_n
        FROM orders o JOIN users u ON u.id = o.user_id
        WHERE 1=1";
$params = [];
if ($durum !== '') {
    $sql .= ' AND o.status = ?';
    $params[] = $durum;
}
if ($tarih === 'bugun') {
    $sql .= ' AND DATE(o.created_at) = CURDATE()';
} elseif ($tarih === 'hafta') {
    $sql .= ' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($tarih === 'ay') {
    $sql .= ' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
}
if ($bas !== '') {
    $sql .= ' AND DATE(o.created_at) >= ?';
    $params[] = $bas;
}
if ($bit !== '') {
    $sql .= ' AND DATE(o.created_at) <= ?';
    $params[] = $bit;
}
$sql .= ' ORDER BY o.id DESC';
$st = db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
$ok = flash_ok();

panel_head('admin', 'siparisler', 'Siparişler | Admin', $u);
?>
<?php if ($ok): ?><p class="mb-4 font-bold text-green-700"><?= e($ok) ?></p><?php endif; ?>

<form method="get" action="<?= e(url('admin/siparisler')) ?>" class="card mb-6 p-5">
  <div class="filter-bar">
    <label>Durum
      <select name="durum">
        <option value="">Tümü</option>
        <?php foreach ($durumlar as $d): ?>
          <option value="<?= e($d) ?>" <?= $durum === $d ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Tarih
      <select name="tarih">
        <option value="">Tümü</option>
        <option value="bugun" <?= $tarih === 'bugun' ? 'selected' : '' ?>>Bugün</option>
        <option value="hafta" <?= $tarih === 'hafta' ? 'selected' : '' ?>>Son 7 gün</option>
        <option value="ay" <?= $tarih === 'ay' ? 'selected' : '' ?>>Son 30 gün</option>
      </select>
    </label>
    <label>Başlangıç
      <input type="date" name="bas" value="<?= e($bas) ?>">
    </label>
    <label>Bitiş
      <input type="date" name="bit" value="<?= e($bit) ?>">
    </label>
    <button class="btn-primary h-10 text-sm">Filtrele</button>
    <?php if ($durum !== '' || $tarih !== '' || $bas !== '' || $bit !== ''): ?>
      <a class="btn-outline h-10 text-sm" href="<?= e(url('admin/siparisler')) ?>">Temizle</a>
    <?php endif; ?>
  </div>
</form>

<div class="card overflow-hidden">
  <div class="border-b px-5 py-3 font-extrabold">Kitap siparişleri</div>
  <?php if (!$rows): ?>
    <p class="dash-empty px-5 py-10">
      <?= ($durum !== '' || $tarih !== '' || $bas !== '' || $bit !== '')
        ? 'Bu filtrelere uyan sipariş yok.'
        : 'Henüz kitap siparişi yok. Satın alınca siparişler burada görünür.' ?>
    </p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>No</th>
          <th>Müşteri</th>
          <th>Kalem</th>
          <th>Tutar</th>
          <th>Ödeme</th>
          <th>Teslimat adresi</th>
          <th>Durum</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $o):
            $ship = function_exists('address_format') ? address_format($o) : '';
            ?>
          <tr>
            <td><a class="font-extrabold text-navy" href="<?= e(siparis_url((int) $o['id'])) ?>">#<?= (int) $o['id'] ?></a></td>
            <td>
              <a class="font-extrabold" href="<?= e(kullanici_url((int) $o['user_id'])) ?>"><?= e((string) $o['name']) ?></a>
              <span class="block text-xs text-muted"><?= e(shop_date((string) $o['created_at'])) ?></span>
            </td>
            <td><?= (int) $o['item_n'] ?></td>
            <td><?= money((int) $o['total']) ?></td>
            <td>
              <span class="<?= shop_status_class((string) ($o['pay_status'] ?: 'odendi')) ?>">
                <?= e(shop_pay_status_label((string) ($o['pay_status'] ?: 'odendi'))) ?>
              </span>
            </td>
            <td class="max-w-xs text-sm"><?= $ship !== '' ? e($ship) : '—' ?></td>
            <td><span class="<?= shop_status_class((string) $o['status']) ?>"><?= e(shop_order_status_label((string) $o['status'])) ?></span></td>
            <td><a class="font-extrabold text-navy" href="<?= e(siparis_url((int) $o['id'])) ?>">Detay</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php panel_foot();
