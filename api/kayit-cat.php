<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$u = current_user();
if (!$u || !in_array((string) ($u['role'] ?? ''), ['ogretmen', 'admin'], true)) {
    json_out(['ok' => false, 'error' => 'forbidden'], 403);
}

$asAdmin = (string) ($u['role'] ?? '') === 'admin';
$action = post('action');

if ($action === 'rename_cat' || $action === 'delete_cat') {
    $gid = (int) post('group_id');
    $from = trim(post('cat_from'));
    if ($gid < 1 || $from === '') {
        json_out(['ok' => false, 'error' => 'cat'], 422);
    }
    if (!$asAdmin) {
        $owned = function_exists('group_owned_ids') ? group_owned_ids((int) $u['id']) : [];
        if (!in_array($gid, $owned, true)) {
            json_out(['ok' => false, 'error' => 'forbidden'], 403);
        }
    }
    if (function_exists('ensure_recordings_lesson_cat_schema')) {
        ensure_recordings_lesson_cat_schema();
    }
    $rows = recording_recs_matching_heading($gid, $from);
    if ($action === 'delete_cat') {
        $n = 0;
        foreach ($rows as $row) {
            academy_delete_recording((int) $row['id'], (int) $u['id'], true);
            $n++;
        }
        json_out(['ok' => true, 'deleted' => $n]);
    }
    $to = trim(post('cat_to'));
    $fam = recording_family_for_group_id($gid);
    if ($fam !== '') {
        $to = recording_normalize_subject($to, $fam) ?: $to;
    }
    if ($to === '') {
        json_out(['ok' => false, 'error' => 'lesson_cat'], 422);
    }
    if (recording_fold_tr($to) === recording_fold_tr($from)) {
        json_out(['ok' => true, 'updated' => 0, 'lesson_cat' => $to]);
    }
    foreach ($rows as $row) {
        recording_apply_lesson_cat(db(), (int) $row['id'], $to);
    }
    json_out(['ok' => true, 'updated' => count($rows), 'lesson_cat' => $to]);
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
