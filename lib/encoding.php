<?php

function apply_utf8_runtime(): void
{
    ini_set('default_charset', 'UTF-8');
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');
    }
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $isApi = str_contains($script, '/api/');
    if ($isApi) {
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
}

function looks_like_mojibake(string $s): bool
{
    return (bool) preg_match('/ÄŸ|Ã¼|ÅŸ|Ã§|Ä±|Ã¶|Ä°|Ã‡|Åž|Ã–|Ãœ|Ã¢|â€™|Â/u', $s);
}

function utf8_from_mojibake(string $s): string
{
    if ($s === '' || !looks_like_mojibake($s)) {
        return $s;
    }
    $bytes = null;
    if (function_exists('iconv')) {
        $bytes = @iconv('UTF-8', 'Windows-1252//IGNORE', $s);
    }
    if (!is_string($bytes) || $bytes === '') {
        $bytes = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
    if (!is_string($bytes) || $bytes === '') {
        $latin = @utf8_decode($s);
        $bytes = is_string($latin) ? $latin : '';
    }
    if (!is_string($bytes) || $bytes === '' || !preg_match('//u', $bytes)) {
        return $s;
    }
    return $bytes;
}

function utf8_salvage(string $s): string
{
    $prev = '';
    $cur = $s;
    for ($i = 0; $i < 3 && $cur !== $prev; $i++) {
        $prev = $cur;
        if (!looks_like_mojibake($cur)) {
            break;
        }
        $cur = utf8_from_mojibake($cur);
    }
    $cur = str_replace(['Â·', '—', '–', '•'], '-', $cur);
    return $cur;
}

function utf8_label_defaults(): array
{
    return [
        'seo_site_title' => 'Online İlahiyat',
        'seo_title_suffix' => ' | Online İlahiyat',
        'seo_default_description' => 'Tefsir, hadis, fıkıh, Arapça canlı dersleri ve kitap mağazası.',
        'seo_keywords' => 'online ilahiyat, tefsir, hadis, fıkıh, arapça, canlı ders',
        'smtp_from_name' => 'Online İlahiyat',
    ];
}

function repair_utf8_data(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (setting('utf8_labels_ok') === '3') {
            repair_package_names();
            return;
        }
    } catch (Throwable) {
        return;
    }

    $defaults = utf8_label_defaults();
    foreach (settings_all() as $k => $v) {
        $v = (string) $v;
        if (!looks_like_mojibake($v)) {
            continue;
        }
        setting_set((string) $k, $defaults[$k] ?? utf8_from_mojibake($v));
    }
    foreach ($defaults as $k => $v) {
        $cur = setting($k);
        if ($cur === '' || looks_like_mojibake($cur)) {
            setting_set($k, $v);
        }
    }

    $jobs = [
        ['users', 'id', ['name', 'city', 'bio']],
        ['programs', 'id', ['title', 'description', 'level', 'hours', 'tag']],
        ['books', 'id', ['title', 'author', 'category', 'description', 'publisher']],
        ['categories', 'id', ['slug', 'name']],
        ['campaigns', 'id', ['title', 'slug', 'description', 'code']],
        ['orders', 'id', ['status', 'ship_mode', 'ship_name', 'ship_city', 'ship_district', 'ship_line']],
        ['addresses', 'id', ['title', 'name', 'city', 'district', 'line']],
        ['student_books', 'id', ['status', 'kind']],
        ['contacts', 'id', ['name', 'message']],
        ['packages', 'id', ['name']],
        ['class_groups', 'id', ['name', 'days', 'description']],
        ['live_rooms', 'id', ['title', 'topic']],
        ['live_schedule', 'id', ['title', 'topic', 'note']],
        ['homework', 'id', ['title', 'due_label']],
        ['leads', 'id', ['name', 'interest']],
        ['messages', 'id', ['body']],
        ['student_questions', 'id', ['body', 'answer', 'guest_name']],
        ['recordings', 'id', ['title']],
        ['tests', 'id', ['title', 'description']],
    ];
    foreach ($jobs as [$table, $pk, $cols]) {
        repair_utf8_table($table, $pk, $cols);
    }

    setting_set('utf8_labels_ok', '3');
    repair_package_names();
}

function package_canonical_name(array $row): string
{
    $name = (string) ($row['name'] ?? '');
    $name = str_replace(['Â·', "\xC2\xB7"], '-', $name);
    $name = str_replace(['yÄ±llÄ±k', 'yÄ±llik'], 'yıllık', $name);
    $name = preg_replace('/\s{2,}/u', ' ', $name) ?? $name;
    return trim($name);
}

function repair_package_names(): void
{
    try {
        $rows = db()->query(
            'SELECT p.id, p.kind, p.name, g.name AS group_name
             FROM packages p
             LEFT JOIN class_groups g ON g.id = p.default_group_id'
        )->fetchAll();
    } catch (Throwable) {
        return;
    }
    $up = db()->prepare('UPDATE packages SET name = ? WHERE id = ?');
    foreach ($rows as $row) {
        if (($row['kind'] ?? '') === 'magaza') {
            continue;
        }
        $new = package_canonical_name($row);
        if ($new !== '' && $new !== (string) $row['name']) {
            $up->execute([$new, (int) $row['id']]);
        }
    }
}

function repair_utf8_table(string $table, string $pk, array $cols): void
{
    try {
        $colSql = implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', array_merge([$pk], $cols)));
        $rows = db()->query('SELECT ' . $colSql . ' FROM `' . $table . '`')->fetchAll();
    } catch (Throwable) {
        return;
    }
    foreach ($rows as $row) {
        $sets = [];
        $vals = [];
        foreach ($cols as $col) {
            if (!array_key_exists($col, $row) || $row[$col] === null) {
                continue;
            }
            $old = (string) $row[$col];
            $new = utf8_from_mojibake($old);
            if ($new !== $old) {
                $sets[] = '`' . $col . '` = ?';
                $vals[] = $new;
            }
        }
        if ($sets === []) {
            continue;
        }
        $vals[] = $row[$pk];
        db()->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE `' . $pk . '` = ?')->execute($vals);
    }
}
