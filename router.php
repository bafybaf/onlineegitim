<?php
require_once __DIR__ . '/lib/bootstrap.php';

$path = trim((string) ($_GET['r'] ?? seo_request_path()), '/');
$path = explode('?', $path)[0];

$static = [
    '' => 'index.php',
    'programlar' => 'programlar.php',
    'kitaplar' => 'kitaplar.php',
    'kadro' => 'kadro.php',
    'iletisim' => 'iletisim.php',
    'giris' => 'giris.php',
    'kayit' => 'kayit.php',
    'giris-magaza' => 'giris-magaza.php',
    'giris-ders' => 'giris-ders.php',
    'kayit-magaza' => 'kayit-magaza.php',
    'kayit-ders' => 'kayit-ders.php',
    'uyelik-magaza' => 'uyelik-magaza.php',
    'uyelik-ders' => 'uyelik-ders.php',
    'google-basla' => 'google-basla.php',
    'google-callback' => 'google-callback.php',
    'wiys' => 'giris-admin.php',
    'kurulum' => 'kurulum.php',
    'sepet' => 'sepet.php',
    'cikis' => 'cikis.php',
    'kvkk' => 'kvkk.php',
    'gizlilik' => 'gizlilik.php',
    'blog' => 'blog.php',
    'sitemap.xml' => 'sitemap.php',
    'robots.txt' => 'robots.php',
];

if (isset($static[$path])) {
    require __DIR__ . '/' . $static[$path];
    exit;
}

if (preg_match('#^(ogrenci|ogretmen|admin|magaza)/?$#', $path, $m)) {
    $panel = __DIR__ . '/' . $m[1] . '/index.php';
    if (is_file($panel)) {
        require $panel;
        exit;
    }
}

if ($path === 'admin/paket/yeni') {
    $_GET['id'] = '0';
    require __DIR__ . '/admin/paket.php';
    exit;
}

if (preg_match('#^admin/paket/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/paket.php';
    exit;
}

if ($path === 'admin/hoca/yeni') {
    $_GET['id'] = '0';
    require __DIR__ . '/admin/hoca.php';
    exit;
}

if (preg_match('#^admin/hoca/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/hoca.php';
    exit;
}

if ($path === 'admin/kullanici/yeni') {
    $_GET['id'] = '0';
    require __DIR__ . '/admin/kullanici.php';
    exit;
}

if (preg_match('#^admin/kullanici/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/kullanici.php';
    exit;
}

if (preg_match('#^admin/grup/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/grup.php';
    exit;
}

if (preg_match('#^ogretmen/grup/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/ogretmen/grup.php';
    exit;
}

if (preg_match('#^ogretmen/ogrenci/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/ogretmen/ogrenci.php';
    exit;
}

if (preg_match('#^admin/siparis/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/siparis.php';
    exit;
}

if ($path === 'admin/program/yeni') {
    $_GET['id'] = '0';
    require __DIR__ . '/admin/program.php';
    exit;
}

if (preg_match('#^admin/program/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/program.php';
    exit;
}

if (preg_match('#^ogrenci/kayit/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/ogrenci/kayit-izle.php';
    exit;
}

if (preg_match('#^hoca/([A-Za-z0-9\-_]+)$#', $path, $m)) {
    $_GET['slug'] = rawurldecode($m[1]);
    require __DIR__ . '/hoca.php';
    exit;
}

if (preg_match('#^blog/([A-Za-z0-9\-_]+)$#', $path, $m)) {
    $_GET['slug'] = rawurldecode($m[1]);
    require __DIR__ . '/blog-detay.php';
    exit;
}

if (preg_match('#^sertifika/([A-Za-z0-9\-]+)$#', $path, $m)) {
    $_GET['code'] = rawurldecode($m[1]);
    require __DIR__ . '/sertifika.php';
    exit;
}

if ($path === 'admin/urun/yeni') {
    $_GET['id'] = '0';
    require __DIR__ . '/admin/urun-duzenle.php';
    exit;
}

if (preg_match('#^admin/urun/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/admin/urun-duzenle.php';
    exit;
}

if (preg_match('#^kitaplar/([A-Za-z0-9\-_]+)$#', $path, $m)) {
    $_GET['kategori'] = rawurldecode($m[1]);
    require __DIR__ . '/kitaplar.php';
    exit;
}

if (preg_match('#^(ogrenci|ogretmen|admin|magaza)/([A-Za-z0-9\-]+)$#', $path, $m)) {
    $panel = __DIR__ . '/' . $m[1] . '/' . $m[2] . '.php';
    if (is_file($panel)) {
        require $panel;
        exit;
    }
}

if (preg_match('#^program/([A-Za-z0-9\-_]+)$#', $path, $m)) {
    $_GET['slug'] = rawurldecode($m[1]);
    require __DIR__ . '/program-detay.php';
    exit;
}

if (preg_match('#^kitap/([A-Za-z0-9\-_]+)$#', $path, $m)) {
    $_GET['slug'] = rawurldecode($m[1]);
    require __DIR__ . '/kitap-detay.php';
    exit;
}

if (preg_match('#^canli-sinif/(\d+)$#', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/canli-sinif.php';
    exit;
}

if (preg_match('#^odeme/([A-Za-z0-9]+)$#', $path, $m)) {
    $_GET['oid'] = $m[1];
    require __DIR__ . '/odeme.php';
    exit;
}

if (preg_match('#^odeme-sonuc/(ok|hata)/([A-Za-z0-9]+)$#', $path, $m)) {
    $_GET['durum'] = $m[1];
    $_GET['oid'] = $m[2];
    require __DIR__ . '/odeme-sonuc.php';
    exit;
}

if ($path === 'odeme-sonuc') {
    require __DIR__ . '/odeme-sonuc.php';
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Sayfa bulunamadı</title></head><body><p>Sayfa bulunamadı.</p></body></html>';
