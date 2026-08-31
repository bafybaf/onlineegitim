<?php
function seo_panel_areas(): array
{
    return ['ogrenci', 'ogretmen', 'admin', 'magaza'];
}

function seo_is_reserved_path(string $rel): bool
{
    $rel = strtolower(ltrim(explode('?', $rel)[0], '/'));
    $first = explode('/', $rel)[0] ?? '';
    $reserved = [
        'api', 'assets', 'lib', 'includes', 'storage', 'vendor', 'config',
        'router.php',
    ];
    return $first !== '' && in_array($first, $reserved, true);
}

function seo_pretty_panel_path(string $file, string $query = ''): ?string
{
    $file = strtolower(ltrim($file, '/'));
    if (!preg_match('#^(ogrenci|ogretmen|admin|magaza)(?:/(.*))?$#', $file, $m)) {
        return null;
    }
    $area = $m[1];
    $rest = $m[2] ?? '';
    if ($rest === '' || $rest === 'index.php') {
        $out = $area;
    } elseif (str_ends_with($rest, '.php')) {
        $out = $area . '/' . substr($rest, 0, -4);
    } else {
        $out = $area . '/' . $rest;
    }
    return $query !== '' ? $out . '?' . $query : $out;
}

function pretty_path(string $path): string
{
    $path = ltrim($path, '/');
    $qpos = strpos($path, '?');
    $file = $qpos === false ? $path : substr($path, 0, $qpos);
    $query = $qpos === false ? '' : substr($path, $qpos + 1);
    $params = [];
    if ($query !== '') {
        parse_str($query, $params);
    }
    if (seo_is_reserved_path($file)) {
        return $path;
    }
    if ($file === 'admin/paket.php' && isset($params['id']) && (int) $params['id'] === 0) {
        return 'admin/paket/yeni';
    }
    if ($file === 'admin/paket.php' && !empty($params['id'])) {
        return 'admin/paket/' . (int) $params['id'];
    }
    if ($file === 'admin/hoca.php' && isset($params['id']) && (int) $params['id'] === 0) {
        return 'admin/hoca/yeni';
    }
    if ($file === 'admin/hoca.php' && !empty($params['id'])) {
        return 'admin/hoca/' . (int) $params['id'];
    }
    if ($file === 'admin/kullanici.php' && isset($params['id']) && (int) $params['id'] === 0) {
        return 'admin/kullanici/yeni';
    }
    if ($file === 'admin/kullanici.php' && !empty($params['id'])) {
        return 'admin/kullanici/' . (int) $params['id'];
    }
    if ($file === 'admin/grup.php' && !empty($params['id'])) {
        return 'admin/grup/' . (int) $params['id'];
    }
    if ($file === 'ogretmen/grup.php' && !empty($params['id'])) {
        return 'ogretmen/grup/' . (int) $params['id'];
    }
    if ($file === 'ogretmen/ogrenci.php' && !empty($params['id'])) {
        return 'ogretmen/ogrenci/' . (int) $params['id'];
    }
    if ($file === 'admin/siparis.php' && !empty($params['id'])) {
        $sid = (int) $params['id'];
        unset($params['id']);
        $extra = $params ? http_build_query($params) : '';
        return 'admin/siparis/' . $sid . ($extra !== '' ? '?' . $extra : '');
    }
    if ($file === 'admin/program.php' && isset($params['id']) && (int) $params['id'] === 0) {
        return 'admin/program/yeni';
    }
    if ($file === 'admin/program.php' && !empty($params['id'])) {
        return 'admin/program/' . (int) $params['id'];
    }
    if ($file === 'ogrenci/kayit-izle.php' && !empty($params['id'])) {
        return 'ogrenci/kayit/' . (int) $params['id'];
    }
    if ($file === 'ogretmen/kayit-izle.php' && !empty($params['id'])) {
        return 'ogretmen/kayit/' . (int) $params['id'];
    }
    if ($file === 'hoca.php' && !empty($params['slug'])) {
        return 'hoca/' . rawurlencode((string) $params['slug']);
    }
    if ($file === 'blog-detay.php' && !empty($params['slug'])) {
        return 'blog/' . rawurlencode((string) $params['slug']);
    }
    if ($file === 'sertifika.php' && !empty($params['code'])) {
        return 'sertifika/' . rawurlencode((string) $params['code']);
    }
    if (($file === 'admin/urun-duzenle.php' || $file === 'admin/urun.php') && !empty($params['id'])) {
        return 'admin/urun/' . (int) $params['id'];
    }
    if ($file === 'admin/urun-duzenle.php' || $file === 'admin/urun.php') {
        return 'admin/urun/yeni';
    }
    if ($file === 'kitaplar.php' && !empty($params['kategori'])) {
        $cat = rawurlencode((string) $params['kategori']);
        unset($params['kategori']);
        $extra = $params ? http_build_query($params) : '';
        return 'kitaplar/' . $cat . ($extra !== '' ? '?' . $extra : '');
    }
    $panel = seo_pretty_panel_path($file, $query);
    if ($panel !== null) {
        return $panel;
    }
    if ($file === '' || $file === 'index.php') {
        return '';
    }
    $static = [
        'programlar.php' => 'programlar',
        'kitaplar.php' => 'kitaplar',
        'kadro.php' => 'kadro',
        'iletisim.php' => 'iletisim',
        'giris.php' => 'giris',
        'kayit.php' => 'kayit',
        'giris-magaza.php' => 'giris-magaza',
        'giris-ders.php' => 'giris-ders',
        'giris-admin.php' => 'wiys',
        'kurulum.php' => 'kurulum',
        'kayit-magaza.php' => 'kayit-magaza',
        'kayit-ders.php' => 'kayit-ders',
        'uyelik-magaza.php' => 'uyelik-magaza',
        'uyelik-ders.php' => 'uyelik-ders',
        'google-basla.php' => 'google-basla',
        'google-callback.php' => 'google-callback',
        'sepet.php' => 'sepet',
        'cikis.php' => 'cikis',
        'kvkk.php' => 'kvkk',
        'gizlilik.php' => 'gizlilik',
        'blog.php' => 'blog',
        'sitemap.php' => 'sitemap.xml',
        'robots.php' => 'robots.txt',
    ];
    if (isset($static[$file])) {
        return $query !== '' ? $static[$file] . '?' . $query : $static[$file];
    }
    if ($file === 'program-detay.php' && !empty($params['slug'])) {
        return 'program/' . rawurlencode((string) $params['slug']);
    }
    if ($file === 'kitap-detay.php' && !empty($params['slug'])) {
        return 'kitap/' . rawurlencode((string) $params['slug']);
    }
    if ($file === 'canli-sinif.php') {
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            $extra = $params;
            unset($extra['id']);
            $out = 'canli-sinif/' . $id;
            return $extra ? $out . '?' . http_build_query($extra) : $out;
        }
    }
    if ($file === 'odeme.php') {
        $oid = seo_safe_oid((string) ($params['oid'] ?? ''));
        if ($oid !== '') {
            return 'odeme/' . $oid;
        }
    }
    if ($file === 'odeme-sonuc.php') {
        return seo_odeme_sonuc_path((string) ($params['durum'] ?? 'ok'), (string) ($params['oid'] ?? ''));
    }
    return $path;
}

function seo_safe_oid(string $oid): string
{
    $oid = trim($oid);
    return preg_match('/^[A-Za-z0-9]+$/', $oid) ? $oid : '';
}

function seo_odeme_sonuc_path(string $durum = 'ok', string $oid = ''): string
{
    $durum = $durum === 'hata' ? 'hata' : 'ok';
    $oid = seo_safe_oid($oid);
    if ($oid === '') {
        return 'odeme-sonuc';
    }
    return 'odeme-sonuc/' . $durum . '/' . $oid;
}

function canli_url(int $id): string
{
    return rtrim(BASE_URL, '/') . '/canli-sinif/' . max(0, $id);
}

function odeme_url(string $oid): string
{
    $oid = seo_safe_oid($oid);
    if ($oid === '') {
        return rtrim(BASE_URL, '/') . '/odeme';
    }
    return rtrim(BASE_URL, '/') . '/odeme/' . $oid;
}

function odeme_sonuc_url(string $durum = 'ok', string $oid = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . seo_odeme_sonuc_path($durum, $oid);
}

function page_url(string $name, string $slug = ''): string
{
    $base = rtrim(BASE_URL, '/');
    if ($name === 'program' && $slug !== '') {
        return $base . '/program/' . rawurlencode($slug);
    }
    if ($name === 'kitap' && $slug !== '') {
        return $base . '/kitap/' . rawurlencode($slug);
    }
    if ($name === 'hoca' && $slug !== '') {
        return $base . '/hoca/' . rawurlencode($slug);
    }
    if ($name === 'blog' && $slug !== '') {
        return $base . '/blog/' . rawurlencode($slug);
    }
    $routes = [
        'home' => '',
        'programlar' => 'programlar',
        'kitaplar' => 'kitaplar',
        'kadro' => 'kadro',
        'iletisim' => 'iletisim',
        'giris' => 'giris',
        'kayit' => 'kayit',
        'giris-magaza' => 'giris-magaza',
        'giris-ders' => 'giris-ders',
        'kayit-magaza' => 'kayit-magaza',
        'kayit-ders' => 'kayit-ders',
        'uyelik-magaza' => 'uyelik-magaza',
        'uyelik-ders' => 'uyelik-ders',
        'google-basla' => 'google-basla',
        'google-callback' => 'google-callback',
        'wiys' => 'wiys',
        'kurulum' => 'kurulum',
        'sepet' => 'sepet',
        'cikis' => 'cikis',
        'kvkk' => 'kvkk',
        'gizlilik' => 'gizlilik',
        'blog' => 'blog',
    ];
    $path = $routes[$name] ?? ltrim($name, '/');
    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function canonical_base(): string
{
    $base = rtrim(setting('seo_canonical_base'), '/');
    if ($base === '') {
        $base = rtrim(setting('site_url'), '/');
    }
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = ($https ? 'https' : 'http') . '://' . $host . rtrim(BASE_URL, '/');
    }
    return $base;
}

function canonical_url(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $prefix = rtrim(BASE_URL, '/');
    $rel = $path;
    if ($prefix !== '' && str_starts_with($path, $prefix)) {
        $rel = substr($path, strlen($prefix));
    }
    $rel = '/' . ltrim((string) $rel, '/');
    if ($rel === '/index.php' || $rel === '/' || $rel === '') {
        return rtrim(canonical_base(), '/') . '/';
    }
    if (str_ends_with($rel, '.php')) {
        $bits = [];
        foreach (['slug', 'id', 'oid', 'durum'] as $k) {
            if (isset($_GET[$k]) && (string) $_GET[$k] !== '') {
                $bits[$k] = (string) $_GET[$k];
            }
        }
        $mapped = pretty_path(ltrim($rel, '/') . ($bits ? '?' . http_build_query($bits) : ''));
        if ($mapped !== ltrim($rel, '/')) {
            $rel = $mapped === '' ? '' : '/' . $mapped;
        }
    }
    return canonical_base() . $rel;
}

function seo_image_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return app_public_url(ltrim($path, '/'));
}

function seo_document_title(string $title): string
{
    $site = setting('seo_site_title', defined('APP_NAME') ? APP_NAME : 'Online İlahiyat');
    $suffix = setting('seo_title_suffix', ' | ' . $site);
    $clean = trim($title);
    foreach ([$suffix, ' | ' . $site, ' — ' . $site, ' - ' . $site] as $tail) {
        $tail = (string) $tail;
        if ($tail !== '' && str_ends_with($clean, $tail)) {
            $clean = trim(substr($clean, 0, -strlen($tail)));
        }
    }
    if ($clean === '' || $clean === $site) {
        return $site;
    }
    if (str_contains($clean, $site) || str_contains($clean, ' | ') || str_contains($clean, ' — ')) {
        return $clean;
    }
    return $clean . $suffix;
}

function seo_request_path(): string
{
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $base = rtrim(BASE_URL, '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    return trim((string) $uri, '/');
}

function seo_hydrate_route(): void
{
    $path = seo_request_path();
    if (preg_match('#^program/([^/]+)$#', $path, $m) && empty($_GET['slug'])) {
        $_GET['slug'] = rawurldecode($m[1]);
    }
    if (preg_match('#^kitap/([^/]+)$#', $path, $m) && empty($_GET['slug'])) {
        $_GET['slug'] = rawurldecode($m[1]);
    }
    if (preg_match('#^canli-sinif/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if (preg_match('#^admin/grup/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if (preg_match('#^ogretmen/grup/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if (preg_match('#^ogretmen/ogrenci/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if (preg_match('#^admin/siparis/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if (preg_match('#^admin/program/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if ($path === 'admin/paket/yeni' && !isset($_GET['id'])) {
        $_GET['id'] = '0';
    }
    if (preg_match('#^admin/paket/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if ($path === 'admin/hoca/yeni' && !isset($_GET['id'])) {
        $_GET['id'] = '0';
    }
    if (preg_match('#^admin/hoca/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if ($path === 'admin/kullanici/yeni' && !isset($_GET['id'])) {
        $_GET['id'] = '0';
    }
    if (preg_match('#^admin/kullanici/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if ($path === 'admin/urun/yeni' && !isset($_GET['id'])) {
        $_GET['id'] = '0';
    }
    if (preg_match('#^admin/urun/(\d+)$#', $path, $m) && empty($_GET['id'])) {
        $_GET['id'] = $m[1];
    }
    if (preg_match('#^kitaplar/([^/]+)$#', $path, $m) && empty($_GET['kategori'])) {
        $_GET['kategori'] = rawurldecode($m[1]);
    }
    if (preg_match('#^odeme/([A-Za-z0-9]+)$#', $path, $m) && empty($_GET['oid'])) {
        $_GET['oid'] = $m[1];
    }
    if (preg_match('#^odeme-sonuc/(ok|hata)/([A-Za-z0-9]+)$#', $path, $m)) {
        if (empty($_GET['durum'])) {
            $_GET['durum'] = $m[1];
        }
        if (empty($_GET['oid'])) {
            $_GET['oid'] = $m[2];
        }
    }
}

function maybe_redirect_legacy_url(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') {
        return;
    }
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    if (!str_contains($path, '.php')) {
        return;
    }
    $rel = seo_request_path();
    if (seo_is_reserved_path($rel)) {
        return;
    }
    $legacy = [
        'index.php' => '',
        'programlar.php' => 'programlar',
        'kitaplar.php' => 'kitaplar',
        'kadro.php' => 'kadro',
        'iletisim.php' => 'iletisim',
        'giris.php' => 'giris',
        'kayit.php' => 'kayit',
        'giris-magaza.php' => 'giris-magaza',
        'giris-ders.php' => 'giris-ders',
        'giris-admin.php' => 'wiys',
        'kurulum.php' => 'kurulum',
        'kayit-magaza.php' => 'kayit-magaza',
        'kayit-ders.php' => 'kayit-ders',
        'uyelik-magaza.php' => 'uyelik-magaza',
        'uyelik-ders.php' => 'uyelik-ders',
        'google-basla.php' => 'google-basla',
        'google-callback.php' => 'google-callback',
        'sepet.php' => 'sepet',
        'cikis.php' => 'cikis',
        'kvkk.php' => 'kvkk',
        'gizlilik.php' => 'gizlilik',
        'blog.php' => 'blog',
    ];
    $target = null;
    $skipQuery = [];
    if (preg_match('#^(ogrenci|ogretmen|admin|magaza)/index\.php$#', $rel, $m)) {
        $target = $m[1];
    } elseif (preg_match('#^admin/paket\.php$#', $rel)) {
        $id = (int) ($_GET['id'] ?? 0);
        if (isset($_GET['id']) && $id < 1) {
            $target = 'admin/paket/yeni';
            $skipQuery[] = 'id';
        } elseif ($id > 0) {
            $target = 'admin/paket/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif (preg_match('#^admin/hoca\.php$#', $rel)) {
        $id = (int) ($_GET['id'] ?? 0);
        if (isset($_GET['id']) && $id < 1) {
            $target = 'admin/hoca/yeni';
            $skipQuery[] = 'id';
        } elseif ($id > 0) {
            $target = 'admin/hoca/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif (preg_match('#^admin/kullanici\.php$#', $rel)) {
        $id = (int) ($_GET['id'] ?? 0);
        if (isset($_GET['id']) && $id < 1) {
            $target = 'admin/kullanici/yeni';
            $skipQuery[] = 'id';
        } elseif ($id > 0) {
            $target = 'admin/kullanici/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif (preg_match('#^admin/siparis\.php$#', $rel)) {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $target = 'admin/siparis/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif (preg_match('#^admin/grup\.php$#', $rel)) {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $target = 'admin/grup/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif (preg_match('#^ogretmen/grup\.php$#', $rel)) {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $target = 'ogretmen/grup/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif (preg_match('#^ogretmen/ogrenci\.php$#', $rel)) {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $target = 'ogretmen/ogrenci/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif (preg_match('#^admin/urun-duzenle\.php$#', $rel) || preg_match('#^admin/urun\.php$#', $rel)) {
        $uid = (int) ($_GET['id'] ?? 0);
        $target = $uid > 0 ? 'admin/urun/' . $uid : 'admin/urun/yeni';
        $skipQuery[] = 'id';
    } elseif (preg_match('#^(ogrenci|ogretmen|admin|magaza)/([A-Za-z0-9\-]+)\.php$#', $rel, $m)) {
        $target = $m[1] . '/' . $m[2];
    } elseif ($rel === 'program-detay.php') {
        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug !== '') {
            $target = 'program/' . rawurlencode($slug);
            $skipQuery[] = 'slug';
        }
    } elseif ($rel === 'kitap-detay.php') {
        $slug = trim((string) ($_GET['slug'] ?? ''));
        if ($slug !== '') {
            $target = 'kitap/' . rawurlencode($slug);
            $skipQuery[] = 'slug';
        }
    } elseif ($rel === 'canli-sinif.php') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            $target = 'canli-sinif/' . $id;
            $skipQuery[] = 'id';
        }
    } elseif ($rel === 'odeme.php') {
        $oid = seo_safe_oid((string) ($_GET['oid'] ?? ''));
        if ($oid !== '') {
            $target = 'odeme/' . $oid;
            $skipQuery[] = 'oid';
        }
    } elseif ($rel === 'odeme-sonuc.php') {
        $target = seo_odeme_sonuc_path((string) ($_GET['durum'] ?? 'ok'), (string) ($_GET['oid'] ?? ''));
        $skipQuery[] = 'durum';
        $skipQuery[] = 'oid';
    } elseif ($rel === 'kitaplar.php') {
        $kat = trim((string) ($_GET['kategori'] ?? ''));
        if ($kat !== '') {
            $target = 'kitaplar/' . rawurlencode($kat);
            $skipQuery[] = 'kategori';
        } else {
            $target = 'kitaplar';
        }
    } elseif (array_key_exists($rel, $legacy)) {
        $target = $legacy[$rel];
    }
    if ($target === null) {
        return;
    }
    $dest = rtrim(BASE_URL, '/') . '/' . ltrim($target, '/');
    $query = [];
    foreach ($_GET as $k => $v) {
        if (in_array((string) $k, $skipQuery, true)) {
            continue;
        }
        $query[$k] = $v;
    }
    if ($query) {
        $dest .= '?' . http_build_query($query);
    }
    header('Location: ' . $dest, true, 301);
    exit;
}
