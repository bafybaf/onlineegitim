<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = current_user();
if (!$u || !can_manage_site_content($u)) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}

$table = post('table');
if (($u['role'] ?? '') === 'ogretmen' && !in_array($table, ['home_slides', 'home_highlights', 'programs', 'books'], true)) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}
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
