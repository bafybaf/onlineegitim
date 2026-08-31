<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$result = iyzico_complete($token);
$payment = $result['payment'] ?? null;
$oid = is_array($payment) ? (string) ($payment['merchant_oid'] ?? '') : '';
if (!empty($result['ok'])) {
    redirect(odeme_sonuc_url('ok', $oid));
}
redirect(odeme_sonuc_url('hata', $oid));
