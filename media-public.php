<?php
$rel = str_replace('\\', '/', (string) ($_GET['f'] ?? ''));
$rel = ltrim($rel, '/');
if ($rel === '' || str_contains($rel, '..') || !preg_match('#^[A-Za-z0-9._\-/]+\.(jpe?g|png|webp)$#i', $rel)) {
    http_response_code(404);
    exit;
}
$abs = realpath(__DIR__ . '/storage/uploads/' . $rel);
$root = realpath(__DIR__ . '/storage/uploads');
if ($abs === false || $root === false || !str_starts_with($abs, $root) || !is_file($abs)) {
    http_response_code(404);
    exit;
}
$mime = @mime_content_type($abs) ?: 'application/octet-stream';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($abs));
header('Cache-Control: public, max-age=86400');
readfile($abs);
