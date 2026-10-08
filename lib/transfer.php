<?php

function transfer_split(string $raw): array
{
    $out = [];
    foreach (preg_split('/[;,|]+/u', $raw) ?: [] as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

function transfer_role(string $raw): string
{
    $t = mb_strtolower(trim($raw), 'UTF-8');
    $map = [
        'ogrenci' => 'ogrenci', 'öğrenci' => 'ogrenci', 'student' => 'ogrenci',
        'ogretmen' => 'ogretmen', 'öğretmen' => 'ogretmen', 'hoca' => 'ogretmen', 'teacher' => 'ogretmen',
        'admin' => 'admin', 'yönetici' => 'admin', 'yonetici' => 'admin',
        'musteri' => 'musteri', 'müşteri' => 'musteri', 'mağaza' => 'musteri', 'magaza' => 'musteri',
    ];
    return $map[$t] ?? '';
}

function transfer_status(string $raw): string
{
    $t = mb_strtolower(trim($raw), 'UTF-8');
    if (in_array($t, ['pasif', 'passive', 'kapalı', 'kapali'], true)) {
        return 'pasif';
    }
    if (in_array($t, ['bekliyor', 'pending'], true)) {
        return 'bekliyor';
    }
    return 'aktif';
}

function transfer_csv_send(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $header, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

function transfer_csv_read(string $tmp): array
{
    $raw = (string) file_get_contents($tmp);
    if ($raw === '') {
        throw new RuntimeException('Dosya boş.');
    }
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $first = fgets($fh);
    if ($first === false) {
        fclose($fh);
        throw new RuntimeException('CSV okunamadı.');
    }
    $delim = (substr_count($first, ';') >= substr_count($first, ',')) ? ';' : ',';
    rewind($fh);
    $header = fgetcsv($fh, 0, $delim);
    if (!is_array($header) || $header === []) {
        fclose($fh);
        throw new RuntimeException('Başlık satırı yok.');
    }
    $map = [];
    foreach ($header as $i => $h) {
        $key = transfer_header_key((string) $h);
        if ($key !== '') {
            $map[$key] = $i;
        }
    }
    $rows = [];
    while (($cols = fgetcsv($fh, 0, $delim)) !== false) {
        if ($cols === [null] || $cols === false) {
            continue;
        }
        $row = [];
        foreach ($map as $key => $i) {
            $row[$key] = trim((string) ($cols[$i] ?? ''));
        }
        if (implode('', $row) === '') {
            continue;
        }
        $rows[] = $row;
        if (count($rows) >= 2000) {
            break;
        }
    }
    fclose($fh);
    return $rows;
}

function transfer_header_key(string $h): string
{
    $t = mb_strtolower(trim($h), 'UTF-8');
    $t = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ'], ['i', 'g', 'u', 's', 'o', 'c', 'i'], $t);
    $t = preg_replace('/[^a-z0-9]+/i', '', $t) ?? '';
    $alias = [
        'ad' => 'ad', 'adsoyad' => 'ad', 'name' => 'ad', 'kisi' => 'ad',
        'eposta' => 'eposta', 'email' => 'eposta', 'mail' => 'eposta',
        'telefon' => 'telefon', 'phone' => 'telefon', 'tel' => 'telefon',
        'rol' => 'rol', 'role' => 'rol',
        'durum' => 'durum', 'status' => 'durum',
        'sehir' => 'sehir', 'city' => 'sehir',
        'gruplar' => 'gruplar', 'grup' => 'grup', 'sinif' => 'grup',
        'sifre' => 'sifre', 'password' => 'sifre',
        'program' => 'program', 'egitim' => 'program',
        'hocalar' => 'hocalar', 'hoca' => 'hocalar', 'ogretmen' => 'hocalar',
        'gunler' => 'gunler', 'dersgunleri' => 'gunler', 'days' => 'gunler',
        'kontenjan' => 'kontenjan', 'cap' => 'kontenjan',
        'aciklama' => 'aciklama', 'description' => 'aciklama',
        'whatsapp' => 'whatsapp', 'whatsappgrubu' => 'whatsapp',
        'ogrenciler' => 'ogrenciler', 'ogrenci' => 'ogrenciler',
    ];
    return $alias[$t] ?? '';
}

function transfer_users_export(?string $q = null, ?string $roleF = null): void
{
    $rows = users_admin_rows();
    $q = trim((string) $q);
    $roleF = (string) $roleF;
    $roles = admin_user_roles();
    if ($q !== '' || ($roleF !== '' && isset($roles[$roleF]))) {
        $rows = array_values(array_filter($rows, static function (array $r) use ($q, $roleF): bool {
            if ($roleF !== '' && ($r['role'] ?? '') !== $roleF) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            $hay = mb_strtolower(($r['name'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' . ($r['phone'] ?? ''), 'UTF-8');
            return str_contains($hay, mb_strtolower($q, 'UTF-8'));
        }));
    }
    $gmap = [];
    try {
        $st = db()->query(
            "SELECT e.student_id, g.name FROM enrollments e JOIN class_groups g ON g.id=e.group_id
             WHERE (e.status IS NULL OR e.status IN ('aktif','bekliyor') OR e.status='')"
        );
        foreach ($st as $row) {
            $gmap[(int) $row['student_id']][] = (string) $row['name'];
        }
    } catch (Throwable) {
        $st = db()->query('SELECT e.student_id, g.name FROM enrollments e JOIN class_groups g ON g.id=e.group_id');
        foreach ($st as $row) {
            $gmap[(int) $row['student_id']][] = (string) $row['name'];
        }
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            (string) $r['name'],
            (string) $r['email'],
            (string) ($r['phone'] ?? ''),
            (string) ($r['role'] ?? 'ogrenci'),
            (string) ($r['status'] ?? 'aktif'),
            (string) ($r['city'] ?? ''),
            implode(', ', array_unique($gmap[(int) $r['id']] ?? [])),
            '',
        ];
    }
    transfer_csv_send('kullanicilar.csv', ['ad', 'eposta', 'telefon', 'rol', 'durum', 'sehir', 'gruplar', 'sifre'], $out);
}

function transfer_users_passwords_export(): void
{
    $rows = $_SESSION['oi_transfer_pass'] ?? [];
    unset($_SESSION['oi_transfer_pass']);
    if (!is_array($rows) || $rows === []) {
        flash_error('İndirilecek yeni şifre yok. İçeri aktarmayı yeniden çalıştırın.');
        redirect('admin/kullanicilar');
    }
    transfer_csv_send('yeni-sifreler.csv', ['eposta', 'sifre'], $rows);
}

function transfer_group_id_by_name(string $name): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }
    $st = db()->prepare('SELECT id FROM class_groups WHERE name = ? ORDER BY id ASC LIMIT 1');
    $st->execute([$name]);
    return (int) ($st->fetchColumn() ?: 0);
}

function transfer_users_import(int $actorId): string
{
    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        throw new RuntimeException('CSV dosyası seçin.');
    }
    $rows = transfer_csv_read($_FILES['file']['tmp_name']);
    if ($rows === []) {
        throw new RuntimeException('Aktarılacak satır yok.');
    }
    $added = 0;
    $updated = 0;
    $skip = 0;
    $notes = [];
    $newPass = [];
    $pdo = db();
    foreach ($rows as $i => $row) {
        $line = $i + 2;
        try {
            $email = strtolower(trim((string) ($row['eposta'] ?? '')));
            $name = trim((string) ($row['ad'] ?? ''));
            if ($email === '' && $name === '') {
                $skip++;
                continue;
            }
            $role = transfer_role((string) ($row['rol'] ?? 'ogrenci')) ?: 'ogrenci';
            $status = transfer_status((string) ($row['durum'] ?? 'aktif'));
            $pass = (string) ($row['sifre'] ?? '');
            $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            $id = (int) ($st->fetchColumn() ?: 0);
            $isNew = $id < 1;
            if ($isNew && $pass === '') {
                $pass = bin2hex(random_bytes(5));
                $newPass[] = [$email, $pass];
            }
            $id = admin_save_user($id, [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'phone' => $row['telefon'] ?? '',
                'city' => $row['sehir'] ?? '',
                'bio' => '',
                'role' => $role,
                'status' => $status,
                'password' => $pass,
            ], $actorId);
            if ($isNew) {
                $added++;
            } else {
                $updated++;
            }
            foreach (transfer_split((string) ($row['gruplar'] ?? '')) as $gname) {
                $gid = transfer_group_id_by_name($gname);
                if ($gid < 1) {
                    $notes[] = 'Satır ' . $line . ': grup bulunamadı (' . $gname . ')';
                    continue;
                }
                if ($role !== 'ogrenci') {
                    continue;
                }
                $err = group_add_student($gid, $id);
                if ($err !== '' && !str_contains($err, 'zaten')) {
                    $notes[] = 'Satır ' . $line . ': ' . $err;
                }
            }
        } catch (Throwable $e) {
            $skip++;
            $notes[] = 'Satır ' . $line . ': ' . $e->getMessage();
        }
    }
    if ($newPass !== []) {
        $_SESSION['oi_transfer_pass'] = $newPass;
    }
    $msg = $added . ' yeni, ' . $updated . ' güncellendi';
    if ($skip > 0) {
        $msg .= ', ' . $skip . ' atlandı';
    }
    $msg .= '.';
    if ($notes !== []) {
        $msg .= ' ' . implode(' ', array_slice($notes, 0, 8));
    }
    if ($newPass !== []) {
        $msg .= ' Yeni hesap şifrelerini indirin.';
    }
    return $msg;
}

function transfer_groups_export(): void
{
    $rows = group_list();
    $out = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $teachers = [];
        try {
            $st = db()->prepare(
                'SELECT u.email FROM class_group_teachers cgt JOIN users u ON u.id=cgt.teacher_id WHERE cgt.group_id=? ORDER BY u.name'
            );
            $st->execute([$id]);
            $teachers = $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
        }
        if ($teachers === [] && !empty($r['teacher_name'])) {
            $teachers = [(string) $r['teacher_name']];
        }
        $students = [];
        try {
            $st = db()->prepare(
                "SELECT u.email FROM enrollments e JOIN users u ON u.id=e.student_id
                 WHERE e.group_id=? AND (e.status IS NULL OR e.status<>'silindi') ORDER BY u.name"
            );
            $st->execute([$id]);
            $students = $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            $st = db()->prepare('SELECT u.email FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.group_id=?');
            $st->execute([$id]);
            $students = $st->fetchAll(PDO::FETCH_COLUMN);
        }
        $out[] = [
            (string) $r['name'],
            (string) ($r['program_title'] ?? ''),
            implode(', ', $teachers),
            (string) ($r['days'] ?? ''),
            (string) ((int) ($r['cap'] ?? 10)),
            (string) ($r['description'] ?? ''),
            (string) ($r['whatsapp_url'] ?? ''),
            implode(', ', $students),
        ];
    }
    transfer_csv_send('gruplar.csv', ['grup', 'program', 'hocalar', 'gunler', 'kontenjan', 'aciklama', 'whatsapp', 'ogrenciler'], $out);
}

function transfer_find_program(string $title): int
{
    $title = trim($title);
    if ($title === '') {
        return 0;
    }
    $st = db()->prepare('SELECT id FROM programs WHERE title = ? LIMIT 1');
    $st->execute([$title]);
    $id = (int) ($st->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }
    $st = db()->prepare('SELECT id FROM programs WHERE LOWER(title) = LOWER(?) LIMIT 1');
    $st->execute([$title]);
    return (int) ($st->fetchColumn() ?: 0);
}

function transfer_find_user(string $key, string $role = ''): int
{
    $key = trim($key);
    if ($key === '') {
        return 0;
    }
    if (str_contains($key, '@')) {
        $st = db()->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
        $st->execute([strtolower($key)]);
    } else {
        $st = db()->prepare('SELECT id, role FROM users WHERE name = ? ORDER BY id ASC LIMIT 1');
        $st->execute([$key]);
    }
    $u = $st->fetch();
    if (!$u) {
        return 0;
    }
    if ($role !== '' && ($u['role'] ?? '') !== $role) {
        return 0;
    }
    return (int) $u['id'];
}

function transfer_groups_import(): string
{
    if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        throw new RuntimeException('CSV dosyası seçin.');
    }
    $rows = transfer_csv_read($_FILES['file']['tmp_name']);
    if ($rows === []) {
        throw new RuntimeException('Aktarılacak satır yok.');
    }
    $added = 0;
    $updated = 0;
    $skip = 0;
    $notes = [];
    foreach ($rows as $i => $row) {
        $line = $i + 2;
        try {
            $name = trim((string) ($row['grup'] ?? $row['ad'] ?? ''));
            if ($name === '') {
                $skip++;
                continue;
            }
            $pid = transfer_find_program((string) ($row['program'] ?? ''));
            if ($pid < 1) {
                throw new RuntimeException('Program bulunamadı (' . ($row['program'] ?? '') . ')');
            }
            $teacherIds = [];
            foreach (transfer_split((string) ($row['hocalar'] ?? '')) as $h) {
                $tid = transfer_find_user($h, 'ogretmen');
                if ($tid < 1) {
                    $tid = transfer_find_user($h);
                }
                if ($tid > 0) {
                    $teacherIds[] = $tid;
                } else {
                    $notes[] = 'Satır ' . $line . ': hoca yok (' . $h . ')';
                }
            }
            $teacherIds = array_values(array_unique($teacherIds));
            if ($teacherIds === []) {
                throw new RuntimeException('En az bir hoca girin (e-posta).');
            }
            $students = transfer_split((string) ($row['ogrenciler'] ?? ''));
            $cap = max(1, min(9999, (int) ($row['kontenjan'] ?? 10)));
            $cap = max($cap, count($students), 1);
            $gid = transfer_group_id_by_name($name);
            $data = group_normalize([
                'name' => $name,
                'days' => trim((string) ($row['gunler'] ?? 'Pzt')) ?: 'Pzt',
                'description' => (string) ($row['aciklama'] ?? ''),
                'whatsapp_url' => (string) ($row['whatsapp'] ?? ''),
                'cap' => $cap,
                'program_id' => $pid,
                'teacher_id' => $teacherIds[0],
                'teacher_ids' => $teacherIds,
            ], true);
            $err = group_validate($data, true);
            if ($err !== '') {
                throw new RuntimeException($err);
            }
            $existed = $gid > 0;
            $gid = group_save($data, $gid, true);
            if ($existed) {
                $updated++;
            } else {
                $added++;
            }
            foreach ($students as $em) {
                $sid = transfer_find_user($em, 'ogrenci');
                if ($sid < 1) {
                    $sid = transfer_find_user($em);
                }
                if ($sid < 1) {
                    $notes[] = 'Satır ' . $line . ': öğrenci yok (' . $em . ')';
                    continue;
                }
                $addErr = group_add_student($gid, $sid);
                if ($addErr !== '' && str_contains($addErr, 'Kontenjan')) {
                    db()->prepare('UPDATE class_groups SET cap = cap + 1 WHERE id = ?')->execute([$gid]);
                    $addErr = group_add_student($gid, $sid);
                }
                if ($addErr !== '' && !str_contains($addErr, 'zaten')) {
                    $notes[] = 'Satır ' . $line . ': ' . $addErr;
                }
            }
        } catch (Throwable $e) {
            $skip++;
            $notes[] = 'Satır ' . $line . ': ' . $e->getMessage();
        }
    }
    $msg = $added . ' yeni grup, ' . $updated . ' güncellendi';
    if ($skip > 0) {
        $msg .= ', ' . $skip . ' atlandı';
    }
    $msg .= '.';
    if ($notes !== []) {
        $msg .= ' ' . implode(' ', array_slice($notes, 0, 8));
    }
    return $msg;
}

function transfer_users_handle(int $actorId): void
{
    if (isset($_GET['export']) && (string) $_GET['export'] === 'sifreler') {
        transfer_users_passwords_export();
    }
    if (isset($_GET['export'])) {
        transfer_users_export($_GET['q'] ?? '', $_GET['rol'] ?? '');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'import_csv') {
        try {
            $msg = transfer_users_import($actorId);
            flash_ok($msg);
        } catch (Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('admin/kullanicilar');
    }
}

function transfer_groups_handle(): void
{
    if (isset($_GET['export'])) {
        transfer_groups_export();
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'import_csv') {
        try {
            flash_ok(transfer_groups_import());
        } catch (Throwable $e) {
            flash_error($e->getMessage());
        }
        redirect('admin/gruplar');
    }
}

function transfer_bar(string $exportUrl, string $hint, bool $passBtn = false): string
{
    $pass = $passBtn && !empty($_SESSION['oi_transfer_pass']);
    $html = '<div class="mb-4 flex flex-wrap items-center gap-2">';
    $html .= '<a class="btn-outline text-sm" href="' . e($exportUrl) . '">Dışarı aktar (CSV)</a>';
    $html .= '<form method="post" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">';
    if (function_exists('csrf_field')) {
        $html .= csrf_field();
    }
    $html .= '<input type="hidden" name="action" value="import_csv">';
    $html .= '<input type="file" name="file" accept=".csv,text/csv,text/plain" required class="text-sm">';
    $html .= '<button class="btn-primary text-sm">İçeri aktar</button>';
    $html .= '</form>';
    if ($pass) {
        $html .= '<a class="btn-primary text-sm" href="' . e(url('admin/kullanicilar') . '?export=sifreler') . '">Yeni şifreleri indir</a>';
    }
    $html .= '</div>';
    $html .= '<p class="mb-4 text-xs text-muted">' . e($hint) . '</p>';
    return $html;
}
