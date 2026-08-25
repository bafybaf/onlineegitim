<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$u = require_role('ogrenci');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hid = (int) post('hid');
    try {
        $path = academy_store_upload('file', 'homework', academy_mimes_doc(), 15);
        if ($path) {
            db()->prepare("UPDATE homework_subs SET body=?, file_path=?, status='sent' WHERE homework_id=? AND student_id=?")
                ->execute([post('body'), $path, $hid, $u['id']]);
        } else {
            db()->prepare("UPDATE homework_subs SET body=?, status='sent' WHERE homework_id=? AND student_id=?")
                ->execute([post('body'), $hid, $u['id']]);
        }
        redirect('ogrenci/odevler');
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
$st = db()->prepare(
    "SELECT h.*, s.id sid, s.body, s.status, s.file_path, g.name gname
     FROM homework h
     JOIN homework_subs s ON s.homework_id=h.id AND s.student_id=?
     JOIN class_groups g ON g.id=h.group_id
     JOIN enrollments e ON e.group_id=h.group_id AND e.student_id=?"
);
$st->execute([$u['id'], $u['id']]);
panel_head('ogrenci', 'odevler', 'Ödevler | Öğrenci Paneli', $u);
if ($err) {
    echo '<p class="mb-4 font-bold text-accent">' . e($err) . '</p>';
}
foreach ($st as $h) {
    echo '<article class="card mb-4 p-5"><p class="text-xs font-extrabold uppercase text-navy">' . e($h['gname']) . '</p>';
    echo '<h2 class="font-display mt-1 text-2xl">' . e($h['title']) . '</h2>';
    echo '<p class="text-sm text-muted">Teslim: ' . e($h['due_label']) . ' · ' . e($h['status']) . '</p>';
    if (!empty($h['file_path'])) {
        echo '<p class="mt-2"><a class="font-extrabold text-navy" href="' . e(url('api/dosya.php?tur=odev&id=' . (int) $h['sid'])) . '">Yüklediğiniz dosya</a></p>';
    }
    echo '<form method="post" enctype="multipart/form-data"><input type="hidden" name="hid" value="' . (int) $h['id'] . '">';
    echo '<textarea name="body" class="mt-3 w-full rounded-xl border p-3 text-sm" rows="3">' . e((string) $h['body']) . '</textarea>';
    echo '<label class="mt-2 block text-sm font-bold">Dosya eki (PDF, görsel)<input name="file" type="file" class="mt-1 w-full text-sm"></label>';
    echo '<button class="btn-primary mt-3 text-sm">Teslim et</button></form></article>';
}
panel_foot();
