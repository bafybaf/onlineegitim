<?php

function shop_tr_slug(string $text, string $fallback = 'oge'): string
{
    $map = [
        'ş' => 's', 'Ş' => 's', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g',
        'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c',
    ];
    $s = strtr($text, $map);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    if ($s === '') {
        $s = $fallback;
    }
    return substr($s, 0, 70);
}

function shop_unique_slug(string $text, string $table, ?int $exceptId = null, string $fallback = 'oge'): string
{
    $table = preg_replace('/[^a-z0-9_]/', '', strtolower($table)) ?: 'books';
    $base = shop_tr_slug($text, $fallback);
    $slug = $base;
    $n = 2;
    while (true) {
        $sql = 'SELECT id FROM `' . $table . '` WHERE slug = ?';
        $params = [$slug];
        if ($exceptId !== null && $exceptId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $st = db()->prepare($sql);
        $st->execute($params);
        if (!$st->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
}

function shop_catalog_tables(): array
{
    $out = [];
    try {
        foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
            $out[(string) $row[0]] = true;
        }
    } catch (Throwable) {
    }
    return $out;
}

function shop_catalog_columns(string $table): array
{
    if (function_exists('table_columns')) {
        return table_columns($table);
    }
    if (function_exists('catalog_table_columns')) {
        return catalog_table_columns($table);
    }
    $cols = [];
    try {
        foreach (db()->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll() as $row) {
            $cols[(string) $row['Field']] = true;
        }
    } catch (Throwable) {
    }
    return $cols;
}

function shop_default_categories(): array
{
    return [
        ['slug' => 'tefsir', 'name' => 'Tefsir', 'sort' => 10],
        ['slug' => 'hadis', 'name' => 'Hadis', 'sort' => 20],
        ['slug' => 'fikih', 'name' => 'Fıkıh', 'sort' => 30],
        ['slug' => 'akaid', 'name' => 'Akaid', 'sort' => 40],
        ['slug' => 'arapca', 'name' => 'Arapça', 'sort' => 50],
        ['slug' => 'kiraat', 'name' => 'Kıraat', 'sort' => 60],
        ['slug' => 'siyer', 'name' => 'Siyer', 'sort' => 70],
    ];
}

function ensure_shop_catalog_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = db();
    try {
        $tables = shop_catalog_tables();
        if (!isset($tables['categories'])) {
            $pdo->exec(
                "CREATE TABLE categories (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  slug VARCHAR(80) NOT NULL UNIQUE,
                  name VARCHAR(80) NOT NULL,
                  sort SMALLINT NOT NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        $bookCols = shop_catalog_columns('books');
        foreach ([
            'category' => 'VARCHAR(80) NOT NULL DEFAULT \'\'',
            'category_id' => 'INT UNSIGNED NULL',
            'price' => 'INT UNSIGNED NOT NULL DEFAULT 1',
            'price_old' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'cover' => 'VARCHAR(255) NULL',
            'stock' => 'INT NOT NULL DEFAULT 0',
            'is_digital' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'description' => 'TEXT NULL',
            'pages' => 'SMALLINT UNSIGNED NULL',
            'publisher' => 'VARCHAR(160) NULL',
            'color' => 'VARCHAR(16) NOT NULL DEFAULT \'#1a3fad\'',
        ] as $col => $def) {
            if (!isset($bookCols[$col])) {
                $pdo->exec('ALTER TABLE books ADD COLUMN `' . $col . '` ' . $def);
            }
        }
        try {
            $pdo->exec('ALTER TABLE books ADD CONSTRAINT fk_book_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL');
        } catch (Throwable) {
        }
        $tables = shop_catalog_tables();
        if (!isset($tables['campaigns'])) {
            $pdo->exec(
                "CREATE TABLE campaigns (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  title VARCHAR(160) NOT NULL,
                  slug VARCHAR(80) NOT NULL UNIQUE,
                  description TEXT NULL,
                  type ENUM('yuzde','tutar','kargo') NOT NULL DEFAULT 'yuzde',
                  discount_value INT UNSIGNED NOT NULL DEFAULT 0,
                  code VARCHAR(40) NULL,
                  applies_to ENUM('all','category','book') NOT NULL DEFAULT 'all',
                  category_id INT UNSIGNED NULL,
                  book_id INT UNSIGNED NULL,
                  starts_at DATETIME NULL,
                  ends_at DATETIME NULL,
                  active TINYINT(1) NOT NULL DEFAULT 1,
                  KEY idx_camp_code (code),
                  KEY idx_camp_active (active, starts_at, ends_at),
                  CONSTRAINT fk_camp_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
                  CONSTRAINT fk_camp_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        shop_seed_categories();
        shop_backfill_book_categories();
        shop_seed_campaigns();
    } catch (Throwable) {
        // Şema yoksa sessizce geçilir.
    }
}

function shop_seed_categories(): void
{
    $ins = db()->prepare('INSERT INTO categories (slug, name, sort) SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = ? OR name = ?)');
    foreach (shop_default_categories() as $c) {
        $ins->execute([$c['slug'], $c['name'], $c['sort'], $c['slug'], $c['name']]);
    }
    try {
        $rows = db()->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category <> ''")->fetchAll();
    } catch (Throwable) {
        $rows = [];
    }
    $sort = 80;
    foreach ($rows as $row) {
        $name = trim((string) $row['category']);
        if ($name === '') {
            continue;
        }
        $slug = shop_tr_slug($name, 'kategori');
        $ins->execute([$slug, $name, $sort, $slug, $name]);
        $sort += 10;
    }
}

function shop_backfill_book_categories(): void
{
    $cols = shop_catalog_columns('books');
    if (!isset($cols['category_id'])) {
        return;
    }
    try {
        db()->exec(
            'UPDATE books b
             JOIN categories c ON c.name = b.category
             SET b.category_id = c.id
             WHERE b.category_id IS NULL AND b.category IS NOT NULL AND b.category <> \'\''
        );
        db()->exec(
            'UPDATE books b
             JOIN categories c ON c.id = b.category_id
             SET b.category = c.name
             WHERE (b.category IS NULL OR b.category = \'\') AND b.category_id IS NOT NULL'
        );
    } catch (Throwable) {
    }
}

function shop_seed_campaigns(): void
{
    $tefsirId = shop_category_id_by_slug('tefsir');
    $year = date('Y-m-d H:i:s', time() + 86400 * 365);
    $now = date('Y-m-d H:i:s');
    $st = db()->prepare('SELECT id FROM campaigns WHERE code = ? OR slug = ? LIMIT 1');
    $st->execute(['ILH10', 'ilh10']);
    if (!$st->fetch()) {
        db()->prepare(
            'INSERT INTO campaigns (title, slug, description, type, discount_value, code, applies_to, starts_at, ends_at, active)
             VALUES (?,?,?,?,?,?,?,?,?,1)'
        )->execute([
            'Mağaza kuponu',
            'ilh10',
            'Sepette ILH10 kodunu girince tüm kitaplarda yüzde 10 indirim.',
            'yuzde',
            10,
            'ILH10',
            'all',
            $now,
            $year,
        ]);
    }
    $st2 = db()->prepare('SELECT id FROM campaigns WHERE slug = ? LIMIT 1');
    $st2->execute(['erken-kayit-kitap']);
    if (!$st2->fetch()) {
        db()->prepare(
            'INSERT INTO campaigns (title, slug, description, type, discount_value, code, applies_to, category_id, starts_at, ends_at, active)
             VALUES (?,?,?,?,?,NULL,?,?,?,?,1)'
        )->execute([
            'Erken kayıt kitap',
            'erken-kayit-kitap',
            'Tefsir kitaplarında erken kayıt indirimi. Sepete ekleyince otomatik uygulanır.',
            'yuzde',
            15,
            'category',
            $tefsirId > 0 ? $tefsirId : null,
            $now,
            $year,
        ]);
    }
}

function shop_categories(): array
{
    try {
        return db()->query('SELECT * FROM categories ORDER BY sort, name')->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function shop_category_by_slug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $st = db()->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    $row = $st->fetch();
    return $row ?: null;
}

function shop_category_id_by_slug(string $slug): int
{
    $row = shop_category_by_slug($slug);
    return $row ? (int) $row['id'] : 0;
}

function shop_category_by_id(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function book_category_name(array $book): string
{
    $name = trim((string) ($book['category_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    return trim((string) ($book['category'] ?? ''));
}

function book_category_slug(array $book): string
{
    $slug = trim((string) ($book['category_slug'] ?? ''));
    if ($slug !== '') {
        return $slug;
    }
    $id = (int) ($book['category_id'] ?? 0);
    if ($id > 0) {
        $c = shop_category_by_id($id);
        if ($c) {
            return (string) $c['slug'];
        }
    }
    return shop_tr_slug(book_category_name($book), '');
}

function shop_sync_book_category(int $categoryId): string
{
    $c = shop_category_by_id($categoryId);
    return $c ? (string) $c['name'] : '';
}

function kitaplar_url(string $categorySlug = ''): string
{
    $base = rtrim(BASE_URL, '/') . '/kitaplar';
    $slug = trim($categorySlug);
    return $slug === '' ? $base : $base . '/' . rawurlencode($slug);
}

function urun_yeni_url(): string
{
    return rtrim(BASE_URL, '/') . '/admin/urun/yeni';
}

function campaign_is_live(array $c, ?int $now = null): bool
{
    if ((int) ($c['active'] ?? 0) !== 1) {
        return false;
    }
    $now = $now ?? time();
    $start = trim((string) ($c['starts_at'] ?? ''));
    $end = trim((string) ($c['ends_at'] ?? ''));
    if ($start !== '') {
        $t = strtotime($start);
        if ($t && $t > $now) {
            return false;
        }
    }
    if ($end !== '') {
        $t = strtotime($end);
        if ($t && $t < $now) {
            return false;
        }
    }
    return true;
}

function campaign_applies_to_book(array $c, array $book): bool
{
    $scope = (string) ($c['applies_to'] ?? 'all');
    if ($scope === 'all' || $scope === '') {
        return true;
    }
    if ($scope === 'category') {
        $cid = (int) ($c['category_id'] ?? 0);
        if ($cid < 1) {
            return false;
        }
        return (int) ($book['category_id'] ?? 0) === $cid;
    }
    if ($scope === 'book') {
        return (int) ($book['id'] ?? 0) === (int) ($c['book_id'] ?? 0);
    }
    return false;
}

function campaign_badge_text(array $c): string
{
    return match ((string) ($c['type'] ?? '')) {
        'yuzde' => '%' . (int) ($c['discount_value'] ?? 0),
        'tutar' => money((int) ($c['discount_value'] ?? 0)) . ' indirim',
        'kargo' => 'Ücretsiz kargo',
        default => (string) ($c['title'] ?? 'Kampanya'),
    };
}

function campaign_type_label(string $type): string
{
    return match ($type) {
        'yuzde' => 'Yüzde',
        'tutar' => 'Tutar',
        'kargo' => 'Kargo',
        default => $type,
    };
}

/** @return list<array<string,mixed>> */
function campaign_active_all(): array
{
    try {
        $rows = db()->query('SELECT * FROM campaigns WHERE active = 1 ORDER BY ' . catalog_order_sql('', 'campaigns') . ', ends_at IS NULL, ends_at')->fetchAll();
    } catch (Throwable) {
        return [];
    }
    $out = [];
    foreach ($rows as $c) {
        if (campaign_is_live($c)) {
            $out[] = $c;
        }
    }
    return $out;
}

function campaign_for_book(array $book): ?array
{
    $best = null;
    $score = -1;
    foreach (campaign_active_all() as $c) {
        if (!campaign_applies_to_book($c, $book)) {
            continue;
        }
        $s = match ((string) ($c['applies_to'] ?? 'all')) {
            'book' => 3,
            'category' => 2,
            default => 1,
        };
        if ($s > $score) {
            $best = $c;
            $score = $s;
        }
    }
    return $best;
}

function campaign_banner(): ?array
{
    $all = campaign_active_all();
    if (!$all) {
        return null;
    }
    foreach ($all as $c) {
        if (trim((string) ($c['code'] ?? '')) === '') {
            return $c;
        }
    }
    return $all[0];
}

function campaign_find_by_code(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    try {
        $st = db()->prepare('SELECT * FROM campaigns WHERE code IS NOT NULL AND UPPER(code) = ? LIMIT 1');
        $st->execute([$code]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

function campaign_eligible_subtotal(array $c, array $lines): int
{
    $sub = 0;
    foreach ($lines as $b) {
        if (campaign_applies_to_book($c, $b)) {
            $sub += (int) $b['price'] * max(1, (int) ($b['qty'] ?? 1));
        }
    }
    return $sub;
}

/** @return array{discount:int,ship:int,campaign:?array,code:?string,label:string} */
function campaign_compute(array $c, array $lines, int $subtotal, int $ship): array
{
    $eligible = campaign_eligible_subtotal($c, $lines);
    $discount = 0;
    $newShip = $ship;
    $type = (string) ($c['type'] ?? '');
    $val = (int) ($c['discount_value'] ?? 0);
    if ($type === 'yuzde') {
        $discount = (int) round($eligible * min(100, max(0, $val)) / 100);
    } elseif ($type === 'tutar') {
        $discount = min($eligible, max(0, $val));
    } elseif ($type === 'kargo') {
        $newShip = 0;
    }
    $discount = min($discount, $subtotal);
    $code = strtoupper(trim((string) ($c['code'] ?? '')));
    return [
        'discount' => $discount,
        'ship' => $newShip,
        'campaign' => $c,
        'code' => $code !== '' ? $code : (string) ($c['slug'] ?? ''),
        'label' => (string) ($c['title'] ?? ''),
    ];
}

/** @return array{discount:int,ship:int,campaign:?array,code:?string,label:string} */
function campaign_resolve_for_cart(array $lines, int $subtotal, int $ship, string $code = ''): array
{
    $empty = ['discount' => 0, 'ship' => $ship, 'campaign' => null, 'code' => null, 'label' => ''];
    $code = strtoupper(trim($code));
    if ($code !== '') {
        $byCode = campaign_find_by_code($code);
        if ($byCode && campaign_is_live($byCode)) {
            return campaign_compute($byCode, $lines, $subtotal, $ship);
        }
        if ($code === 'ILH10') {
            return [
                'discount' => $subtotal - (int) round($subtotal * 0.9),
                'ship' => $ship,
                'campaign' => null,
                'code' => 'ILH10',
                'label' => 'Mağaza kuponu',
            ];
        }
    }
    $best = $empty;
    $bestSave = 0;
    foreach (campaign_active_all() as $c) {
        if (trim((string) ($c['code'] ?? '')) !== '') {
            continue;
        }
        $calc = campaign_compute($c, $lines, $subtotal, $ship);
        $save = $calc['discount'] + max(0, $ship - (int) $calc['ship']);
        if ($save > $bestSave) {
            $best = $calc;
            $bestSave = $save;
        }
    }
    return $best;
}

function campaign_first_code_hint(): string
{
    foreach (campaign_active_all() as $c) {
        $code = strtoupper(trim((string) ($c['code'] ?? '')));
        if ($code !== '') {
            return $code;
        }
    }
    return 'ILH10';
}

function books_with_category(?string $categorySlug = null): array
{
    $sql = 'SELECT b.*, c.name AS category_name, c.slug AS category_slug
            FROM books b
            LEFT JOIN categories c ON c.id = b.category_id';
    $params = [];
    if ($categorySlug !== null && $categorySlug !== '') {
        $sql .= ' WHERE c.slug = ?';
        $params[] = $categorySlug;
    }
    $sql .= ' ORDER BY ' . catalog_order_sql('b', 'books');
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
