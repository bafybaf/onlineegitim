<?php
declare(strict_types=1);

function public_program_slugs(): array
{
    return ['dhbt-2026', 'dkab-2027', 'mbsts-2027', 'arapca-yokdil'];
}

function public_programs(): array
{
    try {
        $rows = db()->query('SELECT * FROM programs ORDER BY ' . catalog_order_sql('', 'programs'))->fetchAll();
    } catch (Throwable) {
        return [];
    }
    $by = [];
    foreach ($rows as $p) {
        $by[(string) $p['slug']] = $p;
    }
    $out = [];
    foreach (public_program_slugs() as $slug) {
        if (isset($by[$slug])) {
            $out[] = $by[$slug];
        }
    }
    return $out;
}

/** @return list<array{slug:string,label:string}> */
function nav_course_links(): array
{
    return [
        ['slug' => 'dkab-2027', 'label' => 'DKAB-İHL KURSU (2027)'],
        ['slug' => 'dhbt-2026', 'label' => 'DHBT KURSU'],
        ['slug' => 'mbsts-2027', 'label' => 'MBSTS KURSU (2027)'],
        ['slug' => 'arapca-yokdil', 'label' => 'ARAPÇA YÖKDİL-YDS KURSU'],
    ];
}

/** @return list<array{slug:string,label:string}> */
function nav_exam_menus(): array
{
    return [
        ['slug' => 'dkab-2027', 'label' => 'DKAB-İHL (2027)'],
        ['slug' => 'dhbt-2026', 'label' => 'DHBT'],
        ['slug' => 'mbsts-2027', 'label' => 'MBSTS (2027)'],
    ];
}

/** @return list<array{slug:string,label:string}> */
function footer_course_links(): array
{
    return [
        ['slug' => 'dkab-2027', 'label' => 'ÖABT – DKAB'],
        ['slug' => 'dhbt-2026', 'label' => 'DHBT'],
        ['slug' => 'mbsts-2027', 'label' => 'MBSTS'],
        ['slug' => 'arapca-yokdil', 'label' => 'Arapça YÖKDİL – YDS'],
        ['slug' => 'arapca', 'label' => 'Genel Arapça'],
    ];
}

/** @return list<array{slug:string,label:string}> */
function shop_chip_links(): array
{
    return [
        ['slug' => 'dkab-ihl', 'label' => 'DKAB-İHL'],
        ['slug' => 'dhbt', 'label' => 'DHBT'],
        ['slug' => 'mbsts', 'label' => 'MBSTS'],
        ['slug' => 'kpss', 'label' => 'KPSS'],
    ];
}

function program_legacy_slug(string $slug): string
{
    return match ($slug) {
        'hadis' => 'dhbt-2026',
        'tefsir' => 'dkab-2027',
        'fikih' => 'mbsts-2027',
        default => $slug,
    };
}

/** @return array<string,string> */
function site_program_poster_map(): array
{
    return [
        'dhbt-2026' => 'assets/img/programs/dhbt-2026.jpg',
        'dkab-2027' => 'assets/img/programs/dkab-2027.jpg',
        'mbsts-2027' => 'assets/img/programs/mbsts-2027.jpg',
        'arapca-yokdil' => 'assets/img/programs/arapca-yokdil.jpg',
    ];
}

function site_sync_program_posters(): void
{
    foreach (site_program_poster_map() as $slug => $path) {
        $abs = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!is_file($abs)) {
            continue;
        }
        $row = site_program_by_slug($slug);
        if (!$row) {
            continue;
        }
        $id = (int) $row['id'];
        db()->prepare('UPDATE programs SET image = ? WHERE id = ?')->execute([$path, $id]);
        try {
            db()->prepare('DELETE FROM media_items WHERE owner_type = ? AND owner_id = ?')->execute(['program', $id]);
            if (function_exists('media_add_path')) {
                media_add_path('program', $id, $path);
            }
        } catch (Throwable) {
        }
    }
}

function site_sync_brand_colors(): void
{
    if (setting('brand_palette_rev') === 'black-gold-v1') {
        return;
    }
    $map = [
        '#1a3fad' => '#111111',
        '#0f2a7a' => '#2a2a2a',
        '#0a1a4e' => '#0a0a0a',
        '#12705a' => '#c9a227',
        '#0c5444' => '#c9a227',
    ];
    try {
        $st = db()->prepare('UPDATE books SET color = ? WHERE LOWER(color) = ?');
        foreach ($map as $old => $new) {
            $st->execute([$new, $old]);
        }
        setting_set('brand_palette_rev', 'black-gold-v1');
    } catch (Throwable) {
    }
}

function ensure_public_site_content(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (setting('public_copy_rev') !== 'kurs-2026-v1') {
            site_sync_categories();
            site_sync_programs();
            site_sync_highlights();
            site_sync_campaign();
            site_sync_announcement();
            setting_set('public_copy_rev', 'kurs-2026-v1');
        }
        site_sync_program_posters();
        site_sync_brand_colors();
    } catch (Throwable) {
        // İçerik senkronu başarısız olursa sayfa yine açılsın.
    }
}

function site_sync_categories(): void
{
    $rows = [
        ['dkab-ihl', 'DKAB-İHL (2027)', 1],
        ['dhbt', 'DHBT', 2],
        ['mbsts', 'MBSTS (2027)', 3],
        ['kpss', 'KPSS', 4],
    ];
    $ins = db()->prepare(
        'INSERT INTO categories (slug, name, sort) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), sort = VALUES(sort)'
    );
    foreach ($rows as $row) {
        $ins->execute($row);
    }
    $dhbt = shop_category_by_slug('dhbt');
    if ($dhbt) {
        db()->prepare('UPDATE books SET category = ? WHERE category_id = ?')
            ->execute([(string) $dhbt['name'], (int) $dhbt['id']]);
    }
}

/** @return list<array<string,mixed>> */
function site_featured_program_defs(): array
{
    $copy = catalog_program_copy();
    return [
        [
            'slug' => 'dhbt-2026',
            'aliases' => ['hadis'],
            'title' => '2026 DHBT KURSU',
            'level' => 'DHBT',
            'hours' => 'Canlı ders',
            'price_old' => 0,
            'price_now' => 0,
            'tag' => 'Ücretsiz soru kampı',
            'description' => '2026 DHBT’ye hazırlanıyorsan, bu kamp tam sana göre!',
            'body' => $copy['dhbt-2026'] ?? '',
            'image' => 'assets/img/programs/dhbt-2026.jpg',
        ],
        [
            'slug' => 'dkab-2027',
            'aliases' => ['tefsir'],
            'title' => '2027 DKAB KURSU',
            'level' => 'DKAB-İHL',
            'hours' => 'Canlı ders + video',
            'price_old' => 18000,
            'price_now' => 12600,
            'tag' => 'Full paket',
            'description' => '2027 DKAB’ye hazırlanıyorsan, doğru yerdesin!',
            'body' => $copy['dkab-2027'] ?? '',
            'image' => 'assets/img/programs/dkab-2027.jpg',
        ],
        [
            'slug' => 'mbsts-2027',
            'aliases' => ['fikih'],
            'title' => '2027 MBSTS KURSU',
            'level' => 'MBSTS',
            'hours' => 'Canlı ders + video',
            'price_old' => 19000,
            'price_now' => 13300,
            'tag' => 'Full paket',
            'description' => '2027 MBSTS’ye hazırlanıyorsan, hazırlığını sağlam temeller üzerine kur!',
            'body' => $copy['mbsts-2027'] ?? '',
            'image' => 'assets/img/programs/mbsts-2027.jpg',
        ],
        [
            'slug' => 'arapca-yokdil',
            'aliases' => [],
            'title' => 'ARAPÇA KURSU',
            'level' => 'YÖKDİL – YDS',
            'hours' => 'Canlı ders',
            'price_old' => 22000,
            'price_now' => 15400,
            'tag' => 'Sınav odaklı',
            'description' => 'Arapça YÖKDİL – YDS’ye hazırlanmanın tam zamanı!',
            'body' => $copy['arapca-yokdil'] ?? '',
            'image' => 'assets/img/programs/arapca-yokdil.jpg',
        ],
    ];
}

function site_program_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM programs WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    $row = $st->fetch();
    return $row ?: null;
}

function site_sync_programs(): void
{
    foreach (site_featured_program_defs() as $def) {
        $row = site_program_by_slug($def['slug']);
        if (!$row) {
            foreach ($def['aliases'] as $alias) {
                $row = site_program_by_slug((string) $alias);
                if ($row) {
                    break;
                }
            }
        }
        $img = trim((string) ($def['image'] ?? ''));
        $desc = (string) $def['description'];
        if ($row) {
            db()->prepare(
                'UPDATE programs SET slug=?, title=?, level=?, hours=?, price_old=?, price_now=?, tag=?, description=?, image=? WHERE id=?'
            )->execute([
                $def['slug'],
                $def['title'],
                $def['level'],
                $def['hours'],
                (int) $def['price_old'],
                (int) $def['price_now'],
                $def['tag'],
                $desc,
                $img !== '' ? $img : (string) ($row['image'] ?? ''),
                (int) $row['id'],
            ]);
            continue;
        }
        db()->prepare(
            'INSERT INTO programs (slug, title, level, hours, price_old, price_now, tag, description, image)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $def['slug'],
            $def['title'],
            $def['level'],
            $def['hours'],
            (int) $def['price_old'],
            (int) $def['price_now'],
            $def['tag'],
            $desc,
            $img !== '' ? $img : 'assets/img/programs/arapca.jpg',
        ]);
    }
    db()->prepare("UPDATE programs SET title='Genel Arapça', level='Arapça', tag='Genel Arapça' WHERE slug='arapca'")
        ->execute();
}

function site_sync_highlights(): void
{
    $rows = [
        ['🎓', 'Uzman Akademisyenlerden Dersler', 10],
        ['🎥', 'Canlı Dersler + Video Dersler', 20],
        ['📝', 'Deneme Sınavları', 30],
        ['📚', 'Alan Kitapları', 40],
    ];
    $existing = db()->query('SELECT id FROM home_highlights ORDER BY sort, id')->fetchAll();
    if (count($existing) >= 4) {
        $upd = db()->prepare('UPDATE home_highlights SET mark=?, label=?, active=1, sort=? WHERE id=?');
        foreach ($rows as $i => $row) {
            $upd->execute([$row[0], $row[1], $row[2], (int) $existing[$i]['id']]);
        }
        return;
    }
    db()->exec('DELETE FROM home_highlights');
    $ins = db()->prepare('INSERT INTO home_highlights (mark, label, active, sort) VALUES (?,?,1,?)');
    foreach ($rows as $row) {
        $ins->execute([$row[0], $row[1], $row[2]]);
    }
}

function site_sync_campaign(): void
{
    $title = 'Tüm alan kitaplarından %20 indirim';
    $st = db()->prepare('SELECT id FROM campaigns WHERE slug = ? LIMIT 1');
    $st->execute(['erken-kayit-kitap']);
    $id = (int) ($st->fetchColumn() ?: 0);
    $year = date('Y-m-d H:i:s', time() + 86400 * 400);
    $now = date('Y-m-d H:i:s');
    if ($id > 0) {
        db()->prepare(
            'UPDATE campaigns SET title=?, description=?, type=?, discount_value=?, code=NULL, applies_to=?, category_id=NULL, active=1, starts_at=?, ends_at=? WHERE id=?'
        )->execute([$title, '', 'yuzde', 20, 'all', $now, $year, $id]);
        return;
    }
    db()->prepare(
        'INSERT INTO campaigns (title, slug, description, type, discount_value, code, applies_to, starts_at, ends_at, active)
         VALUES (?,?,?,?,?,NULL,?,?,?,1)'
    )->execute([$title, 'erken-kayit-kitap', '', 'yuzde', 20, 'all', $now, $year]);
}

function site_sync_announcement(): void
{
    $body = "ONLINE İLAHİYAT AÇILDI\n\n"
        . "İlahiyat alanında sınavlara hazırlık artık daha düzenli, daha sistemli ve tek bir platformda!\n"
        . "Online İlahiyat, yeni dönemde adayların ihtiyaç duyduğu eğitimleri uzman akademisyen eğitmenlerle buluşturuyor.\n\n"
        . "• ÖABT – DKAB\n"
        . "• DHBT\n"
        . "• MBSTS\n"
        . "• Arapça YÖKDİL – YDS\n\n"
        . "kursları, alanında uzman akademisyenler tarafından gerçekleştirilecek canlı derslerle sizlerle buluşuyor.\n\n"
        . "Neden Online İlahiyat?\n"
        . "🎓 Uzman akademisyen eğitmenler\n"
        . "🎥 Canlı ve video kayıt dersler\n"
        . "📚 Detaylı ve sınav odaklı konu anlatımları\n"
        . "📝 Soru çözümü ve sınav pratiği\n"
        . "📅 Düzenli ders ve çalışma programı\n"
        . "💻 Tüm eğitim sürecini tek panelden takip imkânı\n\n"
        . "İster ÖABT-DKAB’ye, ister DHBT ve MBSTS’ye, ister Arapça YÖKDİL-YDS’ye hazırlanıyor olun; hedefinize uygun eğitim programıyla sınav sürecinizi uzmanların rehberliğinde yürütün.\n\n"
        . "BİLGİ, TECRÜBE VE DOĞRU EĞİTİM AYNI PLATFORMDA!\n"
        . "Online İlahiyat’ta yeni dönem başladı.\n"
        . "Programları incelemek ve eğitimlere katılmak için: onlineilahiyat.com";
    $st = db()->prepare("SELECT id FROM posts WHERE slug = 'hosgeldiniz' OR title LIKE ? LIMIT 1");
    $st->execute(['%Online İlahiyat açıldı%']);
    $id = (int) ($st->fetchColumn() ?: 0);
    if ($id > 0) {
        db()->prepare('UPDATE posts SET title=?, body=?, published=1 WHERE id=?')
            ->execute(['Online İlahiyat açıldı', $body, $id]);
        return;
    }
    db()->prepare('INSERT INTO posts (slug, title, body, published) VALUES (?,?,?,1)')
        ->execute(['hosgeldiniz', 'Online İlahiyat açıldı', $body]);
}
