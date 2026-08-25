<?php
require_once __DIR__ . '/lib/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$base = rtrim(canonical_base(), '/');
$urls = [
    $base . '/',
    page_url('programlar'),
    page_url('kitaplar'),
    page_url('kadro'),
    page_url('blog'),
    page_url('iletisim'),
    page_url('kvkk'),
    page_url('gizlilik'),
    page_url('kayit-ders'),
    page_url('kayit-magaza'),
];
foreach (db()->query('SELECT slug FROM programs')->fetchAll() as $p) {
    $urls[] = page_url('program', (string) $p['slug']);
}
try {
    foreach (db()->query('SELECT slug FROM books')->fetchAll() as $b) {
        $urls[] = page_url('kitap', (string) $b['slug']);
    }
} catch (Throwable) {
}
try {
    foreach (db()->query("SELECT slug FROM users WHERE role='ogretmen' AND slug IS NOT NULL AND slug <> ''")->fetchAll() as $h) {
        $urls[] = page_url('hoca', (string) $h['slug']);
    }
} catch (Throwable) {
}
try {
    foreach (db()->query('SELECT slug FROM posts WHERE published=1')->fetchAll() as $p) {
        $urls[] = page_url('blog', (string) $p['slug']);
    }
} catch (Throwable) {
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_unique($urls) as $u) {
    echo '  <url><loc>' . htmlspecialchars((string) $u, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>' . "\n";
}
echo '</urlset>';
