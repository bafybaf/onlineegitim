<?php
declare(strict_types=1);

function ensure_home_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS home_slides (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              badge VARCHAR(80) NOT NULL DEFAULT '',
              title VARCHAR(200) NOT NULL,
              title_accent VARCHAR(200) NOT NULL DEFAULT '',
              accent_class VARCHAR(16) NOT NULL DEFAULT 'accent',
              body TEXT NOT NULL,
              btn1_label VARCHAR(80) NOT NULL DEFAULT '',
              btn1_url VARCHAR(255) NOT NULL DEFAULT '',
              btn2_label VARCHAR(80) NOT NULL DEFAULT '',
              btn2_url VARCHAR(255) NOT NULL DEFAULT '',
              btn2_kind VARCHAR(16) NOT NULL DEFAULT 'link',
              image VARCHAR(255) NOT NULL DEFAULT '',
              alt VARCHAR(160) NOT NULL DEFAULT '',
              active TINYINT(1) NOT NULL DEFAULT 1,
              sort SMALLINT NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        db()->exec(
            "CREATE TABLE IF NOT EXISTS home_highlights (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              mark VARCHAR(16) NOT NULL DEFAULT '',
              label VARCHAR(160) NOT NULL,
              active TINYINT(1) NOT NULL DEFAULT 1,
              sort SMALLINT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable) {
        return;
    }
    home_migrate_hero_images();
    home_seed_if_empty();
}

function home_migrate_hero_images(): void
{
    try {
        $has = (int) db()->query("SELECT COUNT(*) FROM home_slides WHERE image LIKE '%hero-1.jpg%' OR image LIKE '%hero-2.jpg%'")->fetchColumn();
    } catch (Throwable) {
        return;
    }
    if ($has > 0) {
        return;
    }
    db()->exec("DELETE FROM home_slides");
    $ins = db()->prepare(
        'INSERT INTO home_slides (badge, title, title_accent, accent_class, body, btn1_label, btn1_url, btn2_label, btn2_url, btn2_kind, image, alt, active, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?)'
    );
    $ins->execute(['', 'Hero', '', 'accent', '', '', 'kayit-ders', '', '', 'link', 'assets/img/hero-1.jpg', 'Online İlahiyat Canlı Dersler', 10]);
    $ins->execute(['', 'Hero', '', 'accent', '', '', 'kitaplar', '', '', 'link', 'assets/img/hero-2.jpg', 'Online İlahiyat Kitaplar', 20]);
}

function home_seed_if_empty(): void
{
    try {
        $n = (int) db()->query('SELECT COUNT(*) FROM home_slides')->fetchColumn();
    } catch (Throwable) {
        return;
    }
    if ($n < 1) {
        $ins = db()->prepare(
            'INSERT INTO home_slides (badge, title, title_accent, accent_class, body, btn1_label, btn1_url, btn2_label, btn2_url, btn2_kind, image, alt, active, sort) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?)'
        );
        $ins->execute([
            '', 'Hero', '', 'accent', '', '', 'kayit-ders', '', '', 'link',
            'assets/img/hero-1.jpg', 'Online İlahiyat Canlı Dersler', 10,
        ]);
        $ins->execute([
            '', 'Hero', '', 'accent', '', '', 'kitaplar', '', '', 'link',
            'assets/img/hero-2.jpg', 'Online İlahiyat Kitaplar', 20,
        ]);
    }
    try {
        $h = (int) db()->query('SELECT COUNT(*) FROM home_highlights')->fetchColumn();
    } catch (Throwable) {
        return;
    }
    if ($h < 1) {
        $insH = db()->prepare('INSERT INTO home_highlights (mark, label, active, sort) VALUES (?,?,1,?)');
        $insH->execute(['10', 'En fazla 10 kişilik sınıf', 10]);
        $insH->execute(['▶', 'Canlı ders + kayıt', 20]);
        $insH->execute(['📚', 'Kitap mağazası', 30]);
        $insH->execute(['✓', 'Ücretsiz tanışma', 40]);
    }
}

function home_slides(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM home_slides';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY sort, id';
        return db()->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function home_highlights(bool $activeOnly = true): array
{
    try {
        $sql = 'SELECT * FROM home_highlights';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY sort, id';
        return db()->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function home_slide(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM home_slides WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function home_highlight(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM home_highlights WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function home_public_href(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || $raw === '#') {
        return '';
    }
    if (preg_match('#^(https?:|mailto:|tel:)#i', $raw)) {
        return $raw;
    }
    return page_url(ltrim($raw, '/'));
}

function home_image_src(string $path): string
{
    $src = function_exists('catalog_media_src') ? catalog_media_src($path) : '';
    if ($src !== '') {
        return $src;
    }
    $path = trim($path);
    return $path !== '' ? url($path) : '';
}

function home_accent_class(string $kind): string
{
    return $kind === 'navy' ? 'text-navy' : 'text-accent';
}

function home_next_sort(string $table): int
{
    $safe = $table === 'home_highlights' ? 'home_highlights' : 'home_slides';
    try {
        return (int) db()->query('SELECT COALESCE(MAX(sort), 0) FROM `' . $safe . '`')->fetchColumn() + 10;
    } catch (Throwable) {
        return 10;
    }
}
