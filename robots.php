<?php
require_once __DIR__ . '/lib/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
$base = rtrim(canonical_base(), '/');
echo "User-agent: *\n";
echo "Disallow: /ogrenci/\n";
echo "Disallow: /ogretmen/\n";
echo "Disallow: /admin/\n";
echo "Disallow: /magaza/\n";
echo "Disallow: /api/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /wiys\n";
echo "Disallow: /kurulum\n";
echo "Allow: /\n";
echo 'Sitemap: ' . $base . "/sitemap.xml\n";
