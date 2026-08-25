<?php
require_once __DIR__ . '/lib/bootstrap.php';
$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
$st = db()->prepare(
    'SELECT c.*, u.name student_name, g.name gname
     FROM certificates c
     JOIN users u ON u.id=c.student_id
     JOIN class_groups g ON g.id=c.group_id
     WHERE c.code=?'
);
$st->execute([$code]);
$c = $st->fetch();
header('Content-Type: text/html; charset=utf-8');
if (!$c) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Belge yok</title></head><body><p>Sertifika bulunamadı.</p></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <title><?= e($c['code']) ?> · Sertifika</title>
  <style>
    body{font-family:Georgia,serif;background:#f5f5f7;margin:0;padding:2rem}
    .sheet{max-width:40rem;margin:0 auto;background:#fff;border:8px solid #1a3fad;padding:3rem;text-align:center}
    h1{font-size:2rem;margin:.5rem 0}
    .code{letter-spacing:.2em;text-transform:uppercase;color:#12705a;font-size:12px}
    @media print{body{background:#fff} .no-print{display:none}}
  </style>
</head>
<body>
  <p class="no-print" style="text-align:center;font-family:sans-serif"><button onclick="window.print()">Yazdır / PDF</button></p>
  <div class="sheet">
    <p class="code">Online İlahiyat · Katılım belgesi</p>
    <h1><?= e($c['student_name']) ?></h1>
    <p><?= e($c['gname']) ?> grubunu yüzde <?= (int) $c['progress'] ?> ilerleme ile tamamlamıştır.</p>
    <p>Belge no: <b><?= e($c['code']) ?></b><br><?= e(date('d.m.Y', strtotime((string) $c['issued_at']))) ?></p>
  </div>
</body>
</html>
