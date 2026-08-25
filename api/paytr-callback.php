<?php
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'FAIL';
    exit;
}

if (!paytr_configured() || !paytr_verify_callback($_POST)) {
    http_response_code(400);
    echo 'PAYTR notification failed: bad hash';
    exit;
}

$oid = (string) ($_POST['merchant_oid'] ?? '');
$payment = payment_by_oid($oid);
if (!$payment) {
    echo 'OK';
    exit;
}

if (($_POST['status'] ?? '') === 'success') {
    payment_fulfill($payment);
} else {
    $reason = (string) ($_POST['failed_reason_msg'] ?? $_POST['failed_reason_code'] ?? 'Ödeme başarısız');
    payment_fail($payment, $reason);
}

echo 'OK';
