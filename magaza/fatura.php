<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u || !in_array($u['role'], ['musteri', 'ogrenci', 'admin'], true)) {
    redirect('giris-magaza');
}
$id = (int) ($_GET['id'] ?? 0);
$sql = 'SELECT * FROM orders WHERE id=?';
$args = [$id];
if ($u['role'] !== 'admin') {
    $sql .= ' AND user_id=?';
    $args[] = (int) $u['id'];
}
$st = db()->prepare($sql);
$st->execute($args);
$o = $st->fetch();
if (!$o) {
    http_response_code(404);
    echo 'Fatura bulunamadı.';
    exit;
}
$items = db()->prepare('SELECT oi.*, b.title FROM order_items oi JOIN books b ON b.id=oi.book_id WHERE oi.order_id=?');
$items->execute([$id]);
$items = $items->fetchAll();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title>Fatura <?= (int) $o['id'] ?></title>
  <style>
    body{font-family:Nunito,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem}
    table{width:100%;border-collapse:collapse}
    td,th{border-top:1px solid #e5e5e7;padding:.5rem 0;text-align:left}
    @media print{.no-print{display:none}}
  </style>
</head>
<body>
  <p class="no-print"><button onclick="window.print()">Yazdır / PDF</button></p>
  <p style="letter-spacing:.2em;text-transform:uppercase;font-size:12px;color:#1a3fad">Online İlahiyat · Sipariş özeti</p>
  <h1>Sipariş #<?= (int) $o['id'] ?></h1>
  <p><?= e((string) ($o['ship_name'] ?? $u['name'])) ?><br><?= e((string) ($o['ship_line'] ?? '')) ?> <?= e((string) ($o['ship_district'] ?? '')) ?> <?= e((string) ($o['ship_city'] ?? '')) ?></p>
  <p>Durum: <?= e((string) $o['status']) ?> · <?= e((string) ($o['merchant_oid'] ?? '')) ?></p>
  <table>
    <thead><tr><th>Ürün</th><th>Adet</th><th>Tutar</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= e($it['title']) ?></td><td><?= (int) $it['qty'] ?></td><td><?= money((int) $it['price']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p><b>Toplam <?= money((int) $o['total']) ?></b></p>
  <p style="color:#6e6e73;font-size:13px">Bu belge e-arşiv fatura değildir; sipariş özetidir.</p>
</body>
</html>
