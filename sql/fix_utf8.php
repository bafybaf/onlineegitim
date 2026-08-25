<?php
/**
 * UTF-8 onarım (PHP + utf8mb4). Windows mysql.exe yönlendirmesi kullanmayın.
 * Çalıştır: C:\xampp\php\php.exe sql/fix_utf8.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
require_once $root . '/lib/db.php';
require_once $root . '/lib/encoding.php';

$pdo = db();
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

function dump_rows(PDO $pdo, string $sql, string $label): array
{
    echo "\n=== $label ===\n";
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo "(tablo yok: " . $e->getMessage() . ")\n";
        return [];
    }
    foreach ($rows as $row) {
        $bits = [];
        foreach ($row as $k => $v) {
            $bits[] = $k . '=' . (string) $v;
        }
        echo implode(' | ', $bits) . "\n";
    }
    return $rows;
}

$beforeSchedule = dump_rows($pdo, 'SELECT id, title, topic FROM live_schedule ORDER BY id', 'live_schedule ÖNCE');
$beforePkg = dump_rows($pdo, 'SELECT id, name FROM packages ORDER BY id', 'packages ÖNCE');

$known = [
    'live_schedule' => [
        1 => ['title' => 'Tefsir A', 'topic' => 'Bakara 21-29'],
        2 => ['title' => 'Tefsir A', 'topic' => 'Bakara 30-39'],
        3 => ['title' => 'Tefsir A', 'topic' => 'Usul notu: nüzul'],
        4 => ['title' => 'Tefsir A', 'topic' => 'Bakara 40-46'],
        5 => ['title' => 'Arapça A', 'topic' => 'Mübteda-haber tekrarı'],
        6 => ['title' => 'Arapça A', 'topic' => 'İsim cümlesi alıştırması'],
        7 => ['title' => 'Arapça A', 'topic' => 'Sıfat tamlaması'],
        8 => ['title' => 'Hadis 1', 'topic' => "Riyazü's-Salihin 13"],
        9 => ['title' => 'Hadis 1', 'topic' => "Riyazü's-Salihin 14"],
    ],
    'live_rooms' => [
        1 => ['title' => 'Tefsir A', 'topic' => 'Bakara 1-20'],
        2 => ['title' => 'Arapça A', 'topic' => 'İsim cümlesi'],
        3 => ['title' => 'Hadis 1', 'topic' => "Riyazü's-Salihin 12"],
    ],
    'class_groups' => [
        1 => ['name' => 'Tefsir A'],
        2 => ['name' => 'Tefsir B'],
        3 => ['name' => 'Arapça A'],
        4 => ['name' => 'Arapça B'],
        5 => ['name' => 'Hadis 1'],
        6 => ['name' => 'Kıraat Atölyesi'],
    ],
    'packages' => [
        1 => ['name' => 'Pasif mağaza paketi'],
        2 => ['name' => 'Tefsir A - yıllık'],
        3 => ['name' => 'Tefsir B - yıllık'],
        4 => ['name' => 'Arapça A - yıllık'],
        5 => ['name' => 'Arapça B - yıllık'],
        6 => ['name' => 'Hadis 1 - yıllık'],
        7 => ['name' => 'Kıraat Atölyesi - yıllık'],
    ],
    'users' => [
        4 => ['name' => 'Ustaz Mehmet Yıldız'],
        5 => ['name' => 'Hafız Ali Şahin'],
        8 => ['name' => 'Merve Yılmaz'],
        11 => ['name' => 'Elif Koç'],
        12 => ['name' => 'Mağaza Müşteri', 'city' => 'İstanbul', 'bio' => 'Kitap mağazası demo hesabı'],
    ],
];

$updated = 0;
foreach ($known as $table => $rows) {
    foreach ($rows as $id => $cols) {
        $sets = [];
        $vals = [];
        foreach ($cols as $col => $val) {
            $sets[] = '`' . $col . '` = ?';
            $vals[] = $val;
        }
        $vals[] = $id;
        try {
            $st = $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $st->execute($vals);
            $updated += $st->rowCount() > 0 ? 1 : 0;
        } catch (Throwable) {
            // Şema yoksa atla
        }
    }
}

$settingsKnown = [
    'seo_site_title' => 'Online İlahiyat',
    'seo_title_suffix' => ' | Online İlahiyat',
    'seo_default_description' => 'Tefsir, hadis, fıkıh, Arapça canlı dersleri ve kitap mağazası.',
    'seo_keywords' => 'online ilahiyat, tefsir, hadis, fıkıh, arapça, canlı ders',
    'smtp_from_name' => 'Online İlahiyat',
];
foreach ($settingsKnown as $k => $v) {
    try {
        $pdo->prepare('INSERT INTO settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$k, $v]);
    } catch (Throwable) {
    }
}

$scan = [
    'users' => ['name', 'city', 'bio'],
    'programs' => ['title', 'description', 'level', 'hours', 'tag'],
    'books' => ['title', 'author', 'category', 'description', 'publisher'],
    'categories' => ['slug', 'name'],
    'campaigns' => ['title', 'slug', 'description', 'code'],
    'class_groups' => ['name', 'days', 'description'],
    'packages' => ['name'],
    'orders' => ['status', 'ship_mode', 'ship_name', 'ship_city', 'ship_district', 'ship_line'],
    'addresses' => ['title', 'name', 'city', 'district', 'line'],
    'student_books' => ['status', 'kind'],
    'live_rooms' => ['title', 'topic'],
    'live_schedule' => ['title', 'topic', 'note'],
    'live_chat' => ['who_label', 'body'],
    'homework' => ['title', 'due_label'],
    'homework_subs' => ['body'],
    'contacts' => ['name', 'message'],
    'leads' => ['name', 'interest'],
    'messages' => ['body'],
    'recordings' => ['title'],
    'tests' => ['title', 'description'],
    'test_questions' => ['body', 'choice_a', 'choice_b', 'choice_c', 'choice_d'],
    'settings' => ['v'],
];

$salvaged = 0;
foreach ($scan as $table => $cols) {
    $pk = $table === 'settings' ? 'k' : 'id';
    try {
        $colSql = implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', array_merge([$pk], $cols)));
        $rows = $pdo->query('SELECT ' . $colSql . ' FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        continue;
    }
    foreach ($rows as $row) {
        $sets = [];
        $vals = [];
        foreach ($cols as $col) {
            if (!array_key_exists($col, $row) || $row[$col] === null) {
                continue;
            }
            $old = (string) $row[$col];
            $new = utf8_salvage($old);
            if ($new !== $old) {
                $sets[] = '`' . $col . '` = ?';
                $vals[] = $new;
            }
        }
        if ($sets === []) {
            continue;
        }
        $vals[] = $row[$pk];
        $pdo->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `' . $pk . '` = ?')->execute($vals);
        $salvaged++;
    }
}

try {
    $rows = $pdo->query(
        'SELECT p.id, p.kind, p.name, g.name AS group_name
         FROM packages p LEFT JOIN class_groups g ON g.id = p.default_group_id'
    )->fetchAll();
    $up = $pdo->prepare('UPDATE packages SET name = ? WHERE id = ?');
    foreach ($rows as $row) {
        $new = package_canonical_name($row);
        if ($new !== '' && $new !== (string) $row['name']) {
            $up->execute([$new, (int) $row['id']]);
        }
    }
} catch (Throwable) {
}

try {
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute(['utf8_labels_ok', '3']);
} catch (Throwable) {
}

dump_rows($pdo, 'SELECT id, title, topic FROM live_schedule ORDER BY id', 'live_schedule SONRA');
dump_rows($pdo, 'SELECT id, name FROM packages ORDER BY id', 'packages SONRA');

echo "\nBilinen demo satır yazıldı: $updated\nMojibake taraması güncelledi: $salvaged\n";
echo "Bitti. Tarayıcıda Ctrl+F5: /ogrenci/takvim ve /kayit-ders\n";
