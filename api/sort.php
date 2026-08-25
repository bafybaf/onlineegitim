<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = current_user();
if (!$u || !is_admin_role($u['role'])) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}

$table = post('table');
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [];
}
try {
    catalog_apply_sort($table, $ids);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 422);
}
json_out(['ok' => true]);
