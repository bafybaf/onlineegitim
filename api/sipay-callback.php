<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$data = sipay_payload();
$oid = sipay_invoice_id_from($data);
$hash = sipay_hash_result($data);
if ($oid === '' && $hash) {
    $oid = (string) $hash['invoice_id'];
}

$wantsHtml = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';

if ($oid === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'FAIL';
    exit;
}

$payment = payment_by_oid($oid);
if (!$payment) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'FAIL';
    exit;
}

$result = sipay_sync_payment($payment, $data);
$fresh = $result['payment'] ?? $payment;
$paid = ($fresh['status'] ?? '') === 'odendi';

if ($wantsHtml) {
    redirect(odeme_sonuc_url($paid ? 'ok' : ((!empty($result['pending']) ? 'ok' : 'hata')), $oid));
}

header('Content-Type: text/plain; charset=utf-8');
echo 'OK';
