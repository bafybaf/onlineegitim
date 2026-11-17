<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = current_user();
if (!$u || !in_array((string) ($u['role'] ?? ''), ['ogretmen', 'admin'], true)) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}

$id = (int) post('id');
$cat = post('lesson_cat');
if ($id < 1) {
    json_out(['ok' => false, 'error' => 'id'], 422);
}
if (function_exists('ensure_recordings_lesson_cat_schema')) {
    ensure_recordings_lesson_cat_schema();
}

$st = db()->prepare(
    'SELECT rec.id, rec.group_id, rec.teacher_id, rec.lesson_cat, g.name gname, p.slug program_slug
     FROM recordings rec
     JOIN class_groups g ON g.id = rec.group_id
     LEFT JOIN programs p ON p.id = g.program_id
     WHERE rec.id = ?
     LIMIT 1'
);
$st->execute([$id]);
$rec = $st->fetch();
if (!$rec) {
    json_out(['ok' => false, 'error' => 'not_found'], 404);
}

$asAdmin = (string) ($u['role'] ?? '') === 'admin';
if (!$asAdmin) {
    $uid = (int) $u['id'];
    $owned = function_exists('group_owned_ids') ? group_owned_ids($uid) : [];
    $inGroup = in_array((int) $rec['group_id'], $owned, true);
    if (!$inGroup && (int) $rec['teacher_id'] !== $uid) {
        json_out(['ok' => false, 'error' => 'forbidden'], 403);
    }
}

$fam = recording_program_family((string) ($rec['program_slug'] ?? ''), (string) ($rec['gname'] ?? ''));
if ($fam === '') {
    json_out(['ok' => false, 'error' => 'family'], 422);
}
$ok = recording_normalize_subject($cat, $fam);
if ($ok === '') {
    json_out(['ok' => false, 'error' => 'lesson_cat'], 422);
}
recording_apply_lesson_cat(db(), $id, $ok);
json_out(['ok' => true, 'id' => $id, 'lesson_cat' => $ok]);
