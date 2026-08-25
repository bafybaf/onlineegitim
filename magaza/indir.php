<?php
require_once __DIR__ . '/../lib/bootstrap.php';
$u = require_role('musteri');
$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    'SELECT sb.id, sb.status, sb.kind, b.id book_id, b.title, b.slug, b.author, b.description, b.is_digital, b.pages, b.publisher
     FROM student_books sb
     JOIN books b ON b.id = sb.book_id
     WHERE sb.id = ? AND sb.user_id = ?'
);
$st->execute([$id, $u['id']]);
$book = $st->fetch();
if (!$book || !shop_book_downloadable($book)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>İndirme yok</title></head><body><p>Bu kitap için indirme yok veya size ait değil.</p></body></html>';
    exit;
}

$slug = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) $book['slug']) ?: 'kitap';
$disk = dirname(__DIR__) . '/storage/books/' . $slug . '.pdf';
if (is_file($disk)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $slug . '.pdf"');
    header('Content-Length: ' . (string) filesize($disk));
    readfile($disk);
    exit;
}

$title = (string) $book['title'];
$html = '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>'
    . e($title) . '</title></head><body style="font-family:Georgia,serif;max-width:40rem;margin:2rem auto;padding:0 1rem">'
    . '<p style="letter-spacing:.2em;text-transform:uppercase;font-size:12px;color:#12705a">Online İlahiyat · Dijital kopya</p>'
    . '<h1>' . e($title) . '</h1>'
    . '<p>' . e((string) $book['author']) . (empty($book['publisher']) ? '' : ' · ' . e((string) $book['publisher'])) . '</p>'
    . '<p>' . nl2br(e((string) ($book['description'] ?: 'Satın aldığınız dijital kopya. Basılı nüsha kargo ile ayrıca gönderilir.'))) . '</p>'
    . '<p style="color:#6e6e73;font-size:13px">Hesap: ' . e((string) $u['email']) . ' · ' . e(date('d.m.Y H:i')) . '</p>'
    . '</body></html>';

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $slug . '.html"');
echo $html;
