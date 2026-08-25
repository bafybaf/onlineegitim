<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = current_user();
if (!$u) {
    json_out(['ok' => false, 'error' => 'login'], 401);
}
$oid = (string) ($_GET['oid'] ?? '');
$payment = payment_by_oid($oid);
if (!$payment || (int) $payment['user_id'] !== (int) $u['id']) {
    json_out(['ok' => false, 'error' => 'not_found'], 404);
}
json_out([
    'ok' => true,
    'status' => $payment['status'],
    'kind' => $payment['kind'],
    'total' => (int) $payment['total'],
    'fail_reason' => $payment['fail_reason'],
]);
