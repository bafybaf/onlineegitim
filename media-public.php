<?php
$rel = str_replace('\\', '/', (string) ($_GET['f'] ?? ''));
$rel = ltrim($rel, '/');
if ($rel === '' || str_contains($rel, '..') || !preg_match('#^[A-Za-z0-9._\-/]+\.(jpe?g|png|webp)$#i', $rel)) {
    http_response_code(404);
    exit;
}
$candidates = [
    realpath(__DIR__ . '/uploads/' . $rel),
    realpath(__DIR__ . '/storage/uploads/' . $rel),
];
$roots = array_values(array_filter([
    realpath(__DIR__ . '/uploads'),
    realpath(__DIR__ . '/storage/uploads'),
]));
$abs = false;
foreach ($candidates as $cand) {
    if ($cand === false || !is_file($cand)) {
        continue;
    }
    foreach ($roots as $root) {
        if (str_starts_with($cand, $root)) {
            $abs = $cand;
            break 2;
        }
    }
}
if ($abs === false) {
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
