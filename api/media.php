<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = current_user();
if (!$u || !is_admin_role($u['role'])) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}

$action = post('action');
$type = post('owner_type');
$ownerId = (int) post('owner_id');
if (!isset(media_owner_types()[$type])) {
    json_out(['ok' => false, 'error' => 'type'], 422);
}

if ($action === 'upload') {
    if ($ownerId < 1) {
        json_out(['ok' => false, 'error' => 'save_first'], 422);
    }
    $table = media_owner_types()[$type];
    $st = db()->prepare('SELECT id, slug FROM `' . str_replace('`', '', $table) . '` WHERE id = ?');
    $st->execute([$ownerId]);
    $row = $st->fetch();
    if (!$row) {
        json_out(['ok' => false, 'error' => 'not_found'], 404);
    }
    $slug = (string) ($row['slug'] ?? $type . $ownerId);
    $field = isset($_FILES['images']) ? 'images' : 'files';
    try {
        media_attach_uploads($type, $ownerId, $field, $slug);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 422);
    }
    json_out(['ok' => true, 'items' => media_json_items($type, $ownerId)]);
}

if ($action === 'delete') {
    try {
        media_delete((int) post('id'), $type, $ownerId);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 422);
    }
    json_out(['ok' => true, 'items' => media_json_items($type, $ownerId)]);
}

if ($action === 'reorder') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    media_reorder($type, $ownerId, $ids);
    json_out(['ok' => true, 'items' => media_json_items($type, $ownerId)]);
}

json_out(['ok' => false, 'error' => 'action'], 422);
