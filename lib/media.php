<?php

function catalog_media_root(): string
{
    return dirname(__DIR__);
}

function catalog_table_columns(string $table, bool $reload = false): array
{
    static $cache = [];
    $key = strtolower($table);
    if ($reload) {
        unset($cache[$key]);
    }
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $cols = [];
    try {
        foreach (db()->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll() as $row) {
            $cols[(string) $row['Field']] = true;
        }
    } catch (Throwable) {
        $cols = [];
    }
    $cache[$key] = $cols;
    return $cols;
}

function ensure_catalog_media_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $bookCols = catalog_table_columns('books');
    if (!isset($bookCols['cover'])) {
        try {
            $after = isset($bookCols['color']) ? ' AFTER color' : '';
            db()->exec('ALTER TABLE books ADD COLUMN cover VARCHAR(255) NULL' . $after);
            catalog_table_columns('books', true);
        } catch (Throwable) {
        }
    }
    $progCols = catalog_table_columns('programs');
    if (!isset($progCols['image'])) {
        try {
            $after = isset($progCols['description']) ? ' AFTER description' : '';
            db()->exec('ALTER TABLE programs ADD COLUMN image VARCHAR(255) NULL' . $after);
            catalog_table_columns('programs', true);
        } catch (Throwable) {
        }
    }
    foreach (['books', 'programs', 'class_groups', 'packages', 'posts', 'campaigns', 'users', 'categories', 'home_slides', 'home_highlights'] as $table) {
        catalog_ensure_sort_column($table);
    }
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS media_items (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              owner_type VARCHAR(32) NOT NULL,
              owner_id INT UNSIGNED NOT NULL,
              path VARCHAR(255) NOT NULL,
              sort SMALLINT NOT NULL DEFAULT 0,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY owner_sort (owner_type, owner_id, sort)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable) {
    }
    catalog_seed_default_media();
    media_seed_from_covers();
}

function catalog_ensure_sort_column(string $table): void
{
    $cols = catalog_table_columns($table);
    if (isset($cols['sort'])) {
        return;
    }
    $safe = str_replace('`', '', $table);
    try {
        db()->exec('ALTER TABLE `' . $safe . '` ADD COLUMN `sort` SMALLINT NOT NULL DEFAULT 0');
        catalog_table_columns($table, true);
        db()->exec('UPDATE `' . $safe . '` SET `sort` = id * 10 WHERE `sort` = 0');
    } catch (Throwable) {
    }
}

function catalog_order_sql(string $alias = '', string $table = ''): string
{
    $a = $alias !== '' ? $alias . '.' : '';
    if ($table !== '' && !isset(catalog_table_columns($table)['sort'])) {
        return $a . 'id';
    }
    return $a . 'sort, ' . $a . 'id';
}

function catalog_seed_default_media(): void
{
    $root = catalog_media_root();
    try {
        $books = db()->query('SELECT id, slug, cover FROM books')->fetchAll();
    } catch (Throwable) {
        $books = [];
    }
    $updB = db()->prepare('UPDATE books SET cover = ? WHERE id = ?');
    foreach ($books as $b) {
        if (trim((string) ($b['cover'] ?? '')) !== '') {
            continue;
        }
        $rel = catalog_existing_file('assets/img/books', (string) $b['slug'], $root);
        if ($rel !== '') {
            $updB->execute([$rel, (int) $b['id']]);
        }
    }
    try {
        $progs = db()->query('SELECT id, slug, image FROM programs')->fetchAll();
    } catch (Throwable) {
        $progs = [];
    }
    $updP = db()->prepare('UPDATE programs SET image = ? WHERE id = ?');
    foreach ($progs as $p) {
        if (trim((string) ($p['image'] ?? '')) !== '') {
            continue;
        }
        $rel = catalog_existing_file('assets/img/programs', (string) $p['slug'], $root);
        if ($rel !== '') {
            $updP->execute([$rel, (int) $p['id']]);
        }
    }
}

function catalog_existing_file(string $dir, string $slug, string $root = ''): string
{
    $root = $root !== '' ? $root : catalog_media_root();
    $slug = preg_replace('/[^A-Za-z0-9\-_]/', '', $slug) ?: '';
    if ($slug === '') {
        return '';
    }
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $rel = $dir . '/' . $slug . '.' . $ext;
        if (is_file($root . '/' . $rel)) {
            return $rel;
        }
    }
    return '';
}

function catalog_media_src(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    $rel = ltrim($path, '/');
    if (str_starts_with($rel, 'uploads/')) {
        $abs = dirname(__DIR__) . '/storage/' . $rel;
        return is_file($abs) ? url($rel) : '';
    }
    $abs = catalog_media_root() . '/' . $rel;
    if (!is_file($abs)) {
        return '';
    }
    return url($rel);
}

function book_cover_src(array $b): string
{
    $gallery = media_public_urls('book', (int) ($b['id'] ?? 0));
    if ($gallery) {
        return $gallery[0];
    }
    $src = catalog_media_src($b['cover'] ?? '');
    if ($src !== '') {
        return $src;
    }
    $slug = (string) ($b['slug'] ?? '');
    if ($slug === '') {
        return '';
    }
    return catalog_media_src(catalog_existing_file('assets/img/books', $slug));
}

function program_image_src(array $p): string
{
    $gallery = media_public_urls('program', (int) ($p['id'] ?? 0));
    if ($gallery) {
        return $gallery[0];
    }
    $src = catalog_media_src($p['image'] ?? '');
    if ($src !== '') {
        return $src;
    }
    $slug = (string) ($p['slug'] ?? '');
    if ($slug === '') {
        return '';
    }
    return catalog_media_src(catalog_existing_file('assets/img/programs', $slug));
}

function book_cover_html(array $b, string $class = '', string $kind = 'card'): string
{
    $src = book_cover_src($b);
    $title = (string) ($b['title'] ?? '');
    $color = (string) ($b['color'] ?? '#1a3fad');
    $cls = trim('book-cover ' . $class);
    if ($src !== '') {
        return '<img src="' . e($src) . '" alt="' . e($title) . '" class="' . e($cls) . '">';
    }
    $fallback = $kind === 'thumb' ? 'book-cover-fallback book-cover-fallback--thumb' : 'book-cover-fallback';
    $label = $kind === 'thumb' ? mb_substr($title, 0, 10) : $title;
    return '<span class="' . e(trim($fallback . ' ' . $class)) . '" style="background:' . e($color) . '">' . e($label) . '</span>';
}

function program_image_html(array $p, string $class = '', string $kind = 'card'): string
{
    $src = program_image_src($p);
    $title = (string) ($p['title'] ?? '');
    $cls = trim('prog-cover ' . $class);
    if ($src !== '') {
        return '<img src="' . e($src) . '" alt="' . e($title) . '" class="' . e($cls) . '">';
    }
    $fallback = $kind === 'thumb' ? 'prog-cover-fallback prog-cover-fallback--thumb' : 'prog-cover-fallback';
    $label = $kind === 'thumb' ? mb_substr($title, 0, 12) : $title;
    return '<span class="' . e(trim($fallback . ' ' . $class)) . '">' . e($label) . '</span>';
}

function catalog_store_upload(string $field, string $subdir, string $slug): ?string
{
    foreach (media_uploaded_files($field) as $file) {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK || ($file['tmp_name'] ?? '') === '') {
            throw new RuntimeException('Görsel yüklenemedi. Dosya 80 MB altında olmalı.');
        }
        return media_store_tmp($file, $subdir, $slug);
    }
    return null;
}

function program_admin_url(int $id): string
{
    return rtrim(BASE_URL, '/') . '/admin/program/' . max(0, $id);
}

function urun_admin_url(int $id): string
{
    return rtrim(BASE_URL, '/') . '/admin/urun/' . max(0, $id);
}

function media_owner_types(): array
{
    return ['book' => 'books', 'program' => 'programs', 'program_body' => 'programs'];
}

function media_subdir(string $type): string
{
    return str_starts_with($type, 'program') ? 'programs' : 'books';
}

function media_uploaded_files(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!isset($f['tmp_name'])) {
        return [];
    }
    if (is_array($f['tmp_name'])) {
        $out = [];
        foreach ($f['tmp_name'] as $i => $tmp) {
            $out[] = [
                'tmp_name' => (string) $tmp,
                'error' => (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'name' => (string) ($f['name'][$i] ?? ''),
                'size' => (int) ($f['size'][$i] ?? 0),
            ];
        }
        return $out;
    }
    return [[
        'tmp_name' => (string) $f['tmp_name'],
        'error' => (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE),
        'name' => (string) ($f['name'] ?? ''),
        'size' => (int) ($f['size'] ?? 0),
    ]];
}

function media_store_tmp(array $file, string $subdir, string $slug): string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE || ($err === UPLOAD_ERR_OK && ($file['tmp_name'] ?? '') === '')) {
        throw new RuntimeException('Görsel seçilmedi.');
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Görsel yüklenemedi.');
    }
    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Görsel yüklenemedi.');
    }
    $info = @getimagesize($tmp);
    if ($info === false) {
        throw new RuntimeException('Geçerli bir görsel yükleyin (JPG, PNG, WEBP).');
    }
    $ok = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (string) ($info['mime'] ?? '');
    if (!isset($ok[$mime])) {
        throw new RuntimeException('Yalnızca JPG, PNG veya WEBP kabul edilir.');
    }
    $slug = preg_replace('/[^A-Za-z0-9\-_]/', '', $slug) ?: 'item';
    $subdir = trim($subdir, '/');
    $rel = 'uploads/' . $subdir . '/' . $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ok[$mime];
    $abs = dirname(__DIR__) . '/storage/' . $rel;
    if (!is_dir(dirname($abs)) && !mkdir(dirname($abs), 0775, true) && !is_dir(dirname($abs))) {
        throw new RuntimeException('Görsel klasörü oluşturulamadı.');
    }
    if (!move_uploaded_file($tmp, $abs)) {
        throw new RuntimeException('Görsel kaydedilemedi.');
    }
    return $rel;
}

function media_items(string $type, int $ownerId): array
{
    if ($ownerId < 1 || !isset(media_owner_types()[$type])) {
        return [];
    }
    try {
        $st = db()->prepare('SELECT * FROM media_items WHERE owner_type = ? AND owner_id = ? ORDER BY sort, id');
        $st->execute([$type, $ownerId]);
        return $st->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function media_public_urls(string $type, int $ownerId): array
{
    $out = [];
    foreach (media_items($type, $ownerId) as $row) {
        $src = catalog_media_src((string) $row['path']);
        if ($src !== '') {
            $out[] = $src;
        }
    }
    return $out;
}

function media_next_sort(string $type, int $ownerId): int
{
    $st = db()->prepare('SELECT COALESCE(MAX(sort), 0) FROM media_items WHERE owner_type = ? AND owner_id = ?');
    $st->execute([$type, $ownerId]);
    return (int) $st->fetchColumn() + 10;
}

function media_add_path(string $type, int $ownerId, string $path): int
{
    if (!isset(media_owner_types()[$type]) || $ownerId < 1 || $path === '') {
        throw new RuntimeException('Görsel kaydedilemedi.');
    }
    $sort = media_next_sort($type, $ownerId);
    db()->prepare('INSERT INTO media_items (owner_type, owner_id, path, sort) VALUES (?,?,?,?)')
        ->execute([$type, $ownerId, $path, $sort]);
    $id = (int) db()->lastInsertId();
    media_sync_cover($type, $ownerId);
    return $id;
}

function media_attach_uploads(string $type, int $ownerId, string $field, string $slug): void
{
    if ($ownerId < 1) {
        return;
    }
    foreach (media_uploaded_files($field) as $file) {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $path = media_store_tmp($file, media_subdir($type), $slug);
        media_add_path($type, $ownerId, $path);
    }
}

function media_delete(int $id, ?string $type = null, int $ownerId = 0): void
{
    $st = db()->prepare('SELECT * FROM media_items WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        return;
    }
    if ($type !== null && ((string) $row['owner_type'] !== $type || (int) $row['owner_id'] !== $ownerId)) {
        throw new RuntimeException('Görsel silinemedi.');
    }
    db()->prepare('DELETE FROM media_items WHERE id = ?')->execute([$id]);
    media_sync_cover((string) $row['owner_type'], (int) $row['owner_id']);
}

function media_delete_owner(string $type, int $ownerId): void
{
    foreach (media_items($type, $ownerId) as $row) {
        media_delete((int) $row['id']);
    }
}

function media_reorder(string $type, int $ownerId, array $ids): void
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
    $up = db()->prepare('UPDATE media_items SET sort = ? WHERE id = ? AND owner_type = ? AND owner_id = ?');
    foreach ($ids as $i => $id) {
        $up->execute([($i + 1) * 10, $id, $type, $ownerId]);
    }
    media_sync_cover($type, $ownerId);
}

function media_sync_cover(string $type, int $ownerId): void
{
    $items = media_items($type, $ownerId);
    $path = $items ? (string) $items[0]['path'] : null;
    if ($type === 'book') {
        db()->prepare('UPDATE books SET cover = ? WHERE id = ?')->execute([$path, $ownerId]);
    } elseif ($type === 'program') {
        db()->prepare('UPDATE programs SET image = ? WHERE id = ?')->execute([$path, $ownerId]);
    }
}

function media_seed_from_covers(): void
{
    try {
        $n = (int) db()->query('SELECT COUNT(*) FROM media_items')->fetchColumn();
    } catch (Throwable) {
        return;
    }
    if ($n > 0) {
        return;
    }
    try {
        foreach (db()->query('SELECT id, cover FROM books WHERE cover IS NOT NULL AND cover <> \'\'')->fetchAll() as $b) {
            media_add_path('book', (int) $b['id'], (string) $b['cover']);
        }
        foreach (db()->query('SELECT id, image FROM programs WHERE image IS NOT NULL AND image <> \'\'')->fetchAll() as $p) {
            media_add_path('program', (int) $p['id'], (string) $p['image']);
        }
    } catch (Throwable) {
    }
}

function media_json_items(string $type, int $ownerId): array
{
    $out = [];
    foreach (media_items($type, $ownerId) as $row) {
        $src = catalog_media_src((string) $row['path']);
        if ($src === '') {
            continue;
        }
        $out[] = ['id' => (int) $row['id'], 'src' => $src, 'sort' => (int) $row['sort']];
    }
    return $out;
}

function gallery_slider_html(array $srcs, string $alt, string $wrapClass, string $imgClass, string $href = ''): string
{
    $srcs = array_values(array_filter($srcs));
    if (!$srcs) {
        return '';
    }
    $many = count($srcs) > 1;
    $html = '<div class="oi-slider ' . e($wrapClass) . '"' . ($many ? ' data-oi-slider' : '') . '>';
    $html .= '<div class="oi-slides">';
    foreach ($srcs as $i => $src) {
        $img = '<img src="' . e($src) . '" alt="' . e($alt) . '" class="' . e($imgClass) . '">';
        $cls = 'oi-slide' . ($i === 0 ? ' is-on' : '');
        if ($href !== '') {
            $html .= '<a class="' . $cls . '" href="' . e($href) . '">' . $img . '</a>';
        } else {
            $html .= '<div class="' . $cls . '">' . $img . '</div>';
        }
    }
    $html .= '</div>';
    if ($many) {
        $html .= '<button type="button" class="oi-nav oi-prev" data-oi-prev aria-label="Önceki">‹</button>';
        $html .= '<button type="button" class="oi-nav oi-next" data-oi-next aria-label="Sonraki">›</button>';
        $html .= '<div class="oi-dots">';
        foreach ($srcs as $i => $_) {
            $html .= '<button type="button" class="oi-dot' . ($i === 0 ? ' is-on' : '') . '" data-oi-dot="' . $i . '" aria-label="Görsel ' . ($i + 1) . '"></button>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function book_gallery_html(array $b, string $kind = 'card', string $href = ''): string
{
    $srcs = media_public_urls('book', (int) ($b['id'] ?? 0));
    if (!$srcs) {
        $one = book_cover_src($b);
        if ($one !== '') {
            $srcs = [$one];
        }
    }
    $wrap = match ($kind) {
        'detail' => 'book-cover-wrap oi-slider--detail',
        'aside' => 'book-cover-wrap book-cover-wrap--aside',
        default => 'book-cover-wrap',
    };
    if (!$srcs) {
        return '<div class="' . e($wrap) . '">' . book_cover_html($b, '', $kind === 'detail' ? 'card' : $kind) . '</div>';
    }
    return gallery_slider_html($srcs, (string) ($b['title'] ?? ''), $wrap, 'book-cover', $href);
}

function program_gallery_html(array $p, string $kind = 'card', string $href = ''): string
{
    $srcs = media_public_urls('program', (int) ($p['id'] ?? 0));
    if (!$srcs) {
        $one = program_image_src($p);
        if ($one !== '') {
            $srcs = [$one];
        }
    }
    $wrap = $kind === 'detail'
        ? 'prog-cover-wrap oi-slider--detail'
        : 'prog-cover-wrap';
    if (!$srcs) {
        return '<div class="' . e($wrap) . '">' . program_image_html($p, '', $kind === 'detail' ? 'card' : $kind) . '</div>';
    }
    return gallery_slider_html($srcs, (string) ($p['title'] ?? ''), $wrap, 'prog-cover', $href);
}

function program_body_gallery_html(array $p): string
{
    $srcs = media_public_urls('program_body', (int) ($p['id'] ?? 0));
    if (!$srcs) {
        return '';
    }
    $html = '<div class="prog-body-pics">';
    foreach ($srcs as $src) {
        $html .= '<img src="' . e($src) . '" alt="' . e((string) ($p['title'] ?? '')) . '">';
    }
    $html .= '</div>';
    return $html;
}

function media_dropzone_html(string $type, int $ownerId, string $field = 'images'): string
{
    $items = $ownerId > 0 ? media_json_items($type, $ownerId) : [];
    $html = '<div class="media-box" data-media-box data-owner="' . e($type) . '" data-id="' . $ownerId . '">';
    $html .= '<label class="dropzone-wrap">';
    $html .= '<input type="file" name="' . e($field) . '[]" accept="image/jpeg,image/png,image/webp" multiple>';
    $html .= '<span class="dropzone-copy"><b>Görselleri bırakın</b> veya seçin. Birden fazla yükleyebilir, tutup sıralayabilirsiniz.</span>';
    $html .= '</label>';
    $html .= '<div class="media-thumbs" data-media-sort>';
    foreach ($items as $it) {
        $html .= media_thumb_html((int) $it['id'], (string) $it['src']);
    }
    $html .= '</div></div>';
    return $html;
}

function media_thumb_html(int $id, string $src): string
{
    return '<div class="media-thumb" draggable="true" data-id="' . $id . '">'
        . '<img src="' . e($src) . '" alt="">'
        . '<button type="button" class="media-del" data-media-del="' . $id . '" aria-label="Sil">×</button>'
        . '</div>';
}

function catalog_sort_tables(): array
{
    return [
        'programs' => "1=1",
        'books' => "1=1",
        'categories' => "1=1",
        'class_groups' => "1=1",
        'packages' => "kind='ders'",
        'posts' => "1=1",
        'campaigns' => "1=1",
        'users' => "1=1",
        'home_slides' => "1=1",
        'home_highlights' => "1=1",
    ];
}

function catalog_apply_sort(string $table, array $ids): void
{
    $map = catalog_sort_tables();
    if (!isset($map[$table])) {
        throw new RuntimeException('Bu liste sıralanamaz.');
    }
    catalog_ensure_sort_column($table);
    $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
    if (!$ids) {
        return;
    }
    $safe = str_replace('`', '', $table);
    $where = $map[$table];
    $up = db()->prepare('UPDATE `' . $safe . '` SET sort = ? WHERE id = ? AND ' . $where);
    foreach ($ids as $i => $id) {
        $up->execute([($i + 1) * 10, $id]);
    }
}
