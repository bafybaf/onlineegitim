<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u) {
    http_response_code(403);
    exit('Giriş gerekli.');
}
$tur = (string) ($_GET['tur'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    exit('Dosya yok.');
}

$rel = '';
$downloadName = 'dosya';
if ($tur === 'video') {
    $st = db()->prepare('SELECT * FROM recordings WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row || empty($row['video_path']) || !academy_can_access_group_file($u, (int) $row['group_id'])) {
        http_response_code(403);
        exit('Yetkiniz yok.');
    }
    $rel = (string) $row['video_path'];
    $downloadName = academy_slug((string) $row['title']);
} elseif ($tur === 'not') {
    $st = db()->prepare('SELECT * FROM lesson_notes WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row || !academy_can_access_group_file($u, (int) $row['group_id'])) {
        http_response_code(403);
        exit('Yetkiniz yok.');
    }
    $rel = (string) $row['file_path'];
    $downloadName = academy_slug((string) $row['title']);
} elseif ($tur === 'odev') {
    $st = db()->prepare(
        'SELECT s.*, h.group_id, h.title FROM homework_subs s JOIN homework h ON h.id = s.homework_id WHERE s.id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row || empty($row['file_path'])) {
        http_response_code(404);
        exit('Dosya yok.');
    }
    $ok = ((int) $row['student_id'] === (int) $u['id']) || academy_can_access_group_file($u, (int) $row['group_id']);
    if (!$ok) {
        http_response_code(403);
        exit('Yetkiniz yok.');
    }
    $rel = (string) $row['file_path'];
    $downloadName = academy_slug((string) $row['title']);
} else {
    http_response_code(404);
    exit('Dosya yok.');
}

$abs = function_exists('academy_file_readable') ? academy_file_readable($rel) : academy_abs_file($rel);
if ($abs === null || !is_file($abs)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}
$ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'pdf' => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
};
$inline = in_array($ext, ['mp4', 'webm', 'mov', 'pdf', 'jpg', 'jpeg', 'png', 'webp'], true);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($abs));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $downloadName . '.' . $ext . '"');
readfile($abs);
