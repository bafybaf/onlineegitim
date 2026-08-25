<?php

function academy_storage(string $subdir = ''): string
{
    $root = dirname(__DIR__) . '/storage';
    if ($subdir !== '') {
        $root .= '/' . trim($subdir, '/');
    }
    if (!is_dir($root)) {
        @mkdir($root, 0755, true);
    }
    return $root;
}

function academy_normalize_wa(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $digits = preg_replace('/\D+/', '', $url) ?? '';
        if (strlen($digits) < 8) {
            return null;
        }
        $url = 'https://wa.me/' . $digits;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return mb_substr($url, 0, 255);
}

function academy_slug(string $text): string
{
    $map = ['ş' => 's', 'Ş' => 's', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c'];
    $text = strtr($text, $map);
    $text = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '');
    return trim($text, '-') ?: 'kayit';
}

function ensure_academy_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = db();
    $add = static function (string $sql) use ($pdo): void {
        try {
            $pdo->exec($sql);
        } catch (Throwable) {
        }
    };
    $add("ALTER TABLE recordings ADD COLUMN video_url VARCHAR(500) NULL");
    $add("ALTER TABLE recordings ADD COLUMN video_path VARCHAR(255) NULL");
    $add("ALTER TABLE users ADD COLUMN slug VARCHAR(80) NULL");
    $add("ALTER TABLE packages ADD COLUMN access_type VARCHAR(32) NOT NULL DEFAULT 'canli_video'");
    $add("ALTER TABLE packages ADD COLUMN gift_book_id INT UNSIGNED NULL");
    $add("ALTER TABLE class_groups ADD COLUMN whatsapp_url VARCHAR(255) NULL");
    $add("ALTER TABLE homework_subs ADD COLUMN file_path VARCHAR(255) NULL");
    $add("ALTER TABLE tests ADD COLUMN kind VARCHAR(20) NOT NULL DEFAULT 'quiz'");
    $add("ALTER TABLE messages ADD COLUMN teacher_id INT UNSIGNED NULL");
    $add("ALTER TABLE enrollments MODIFY COLUMN status ENUM('aktif','pasif','suresi_doldu','silindi') NOT NULL DEFAULT 'aktif'");
    $add("CREATE TABLE IF NOT EXISTS lesson_notes (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      group_id INT UNSIGNED NOT NULL,
      teacher_id INT UNSIGNED NOT NULL,
      title VARCHAR(200) NOT NULL,
      file_path VARCHAR(255) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $add("CREATE TABLE IF NOT EXISTS certificates (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      student_id INT UNSIGNED NOT NULL,
      group_id INT UNSIGNED NOT NULL,
      code VARCHAR(40) NOT NULL UNIQUE,
      issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      progress TINYINT UNSIGNED NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    $add("CREATE TABLE IF NOT EXISTS notifications (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      title VARCHAR(200) NOT NULL,
      body VARCHAR(400) NOT NULL,
      link VARCHAR(255) NULL,
      read_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $add("CREATE TABLE IF NOT EXISTS posts (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      slug VARCHAR(120) NOT NULL UNIQUE,
      title VARCHAR(200) NOT NULL,
      body TEXT NOT NULL,
      published TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    academy_seed_teacher_slugs();
    academy_seed_posts();
    academy_backfill_message_teachers();
}

function academy_seed_teacher_slugs(): void
{
    try {
        $rows = db()->query("SELECT id, name, slug FROM users WHERE role='ogretmen'")->fetchAll();
    } catch (Throwable) {
        return;
    }
    $upd = db()->prepare('UPDATE users SET slug=? WHERE id=?');
    foreach ($rows as $r) {
        if (trim((string) ($r['slug'] ?? '')) !== '') {
            continue;
        }
        $upd->execute([academy_slug((string) $r['name']) . '-' . $r['id'], (int) $r['id']]);
    }
}

function academy_seed_posts(): void
{
    try {
        $n = (int) db()->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        if ($n > 0) {
            return;
        }
        db()->prepare('INSERT INTO posts (slug, title, body, published) VALUES (?,?,?,1)')
            ->execute([
                'hosgeldiniz',
                'Online İlahiyat açıldı',
                "Canlı ders, kayıt izleme, ödev ve test artık tek panelde. Yeni dönem grupları takvimden takip edilir.\n\nKayıt için program sayfasından üyelik alın.",
            ]);
    } catch (Throwable) {
    }
}

function academy_store_upload(string $field, string $subdir, array $mimes, int $maxMb = 80): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $err = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Dosya yüklenemedi.');
    }
    $tmp = (string) $_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Dosya yüklenemedi.');
    }
    $size = (int) ($_FILES[$field]['size'] ?? 0);
    if ($size > $maxMb * 1024 * 1024) {
        throw new RuntimeException('Dosya en fazla ' . $maxMb . ' MB olabilir.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    if (!isset($mimes[$mime])) {
        throw new RuntimeException('Bu dosya türü kabul edilmiyor.');
    }
    $name = bin2hex(random_bytes(8)) . '.' . $mimes[$mime];
    $dir = academy_storage($subdir);
    $abs = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $abs)) {
        throw new RuntimeException('Dosya kaydedilemedi.');
    }
    return $subdir . '/' . $name;
}

function academy_abs_file(string $rel): string
{
    $root = academy_storage();
    $rootReal = realpath($root) ?: $root;
    $rel = str_replace(["\0", '\\'], ['', '/'], $rel);
    $parts = [];
    foreach (explode('/', $rel) as $p) {
        if ($p === '' || $p === '.') {
            continue;
        }
        if ($p === '..') {
            return $rootReal . DIRECTORY_SEPARATOR . '__denied';
        }
        $parts[] = $p;
    }
    $abs = $root . '/' . implode('/', $parts);
    $real = realpath($abs);
    if ($real === false) {
        return $abs;
    }
    $rootNorm = rtrim(str_replace('\\', '/', $rootReal), '/');
    $realNorm = str_replace('\\', '/', $real);
    if ($realNorm !== $rootNorm && !str_starts_with($realNorm, $rootNorm . '/')) {
        return $rootReal . DIRECTORY_SEPARATOR . '__denied';
    }
    return $real;
}

function academy_file_readable(string $rel): ?string
{
    $abs = academy_abs_file($rel);
    $real = realpath($abs);
    $root = realpath(academy_storage());
    if ($real === false || $root === false || !is_file($real) || !is_readable($real)) {
        return null;
    }
    $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
    $realNorm = str_replace('\\', '/', $real);
    if ($realNorm !== $rootNorm && !str_starts_with($realNorm, $rootNorm . '/')) {
        return null;
    }
    $ext = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));
    if (in_array($ext, ['php', 'phtml', 'phar', 'cgi', 'exe', 'shtml', 'htaccess'], true)) {
        return null;
    }
    return $real;
}

function notify_user(int $userId, string $title, string $body, string $link = ''): void
{
    try {
        db()->prepare('INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)')
            ->execute([$userId, $title, $body, $link !== '' ? $link : null]);
    } catch (Throwable) {
    }
}

function notify_group_students(int $groupId, string $title, string $body, string $link = ''): void
{
    $st = db()->prepare('SELECT student_id FROM enrollments WHERE group_id=?');
    $st->execute([$groupId]);
    foreach ($st as $row) {
        notify_user((int) $row['student_id'], $title, $body, $link);
    }
}

function teacher_groups(int $teacherId): array
{
    $st = db()->prepare('SELECT * FROM class_groups WHERE teacher_id=? ORDER BY name');
    $st->execute([$teacherId]);
    return $st->fetchAll();
}

function student_enrolled_group(int $studentId, int $groupId): bool
{
    $st = db()->prepare(
        "SELECT id FROM enrollments
         WHERE student_id=? AND group_id=?
           AND (status = 'aktif' OR status IS NULL)
           AND (expires_at IS NULL OR expires_at > NOW())"
    );
    $st->execute([$studentId, $groupId]);
    return (bool) $st->fetch();
}

function package_access_label(array $pkg): string
{
    return (($pkg['access_type'] ?? 'canli_video') === 'sadece_video') ? 'Yalnızca kayıt' : 'Canlı + kayıt';
}

function certificate_issue(int $studentId, int $groupId, int $progress): ?array
{
    if ($progress < 70) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM certificates WHERE student_id=? AND group_id=?');
    $st->execute([$studentId, $groupId]);
    $row = $st->fetch();
    if ($row) {
        return $row;
    }
    $code = 'OI-' . strtoupper(substr(md5($studentId . '-' . $groupId . '-' . time()), 0, 8));
    db()->prepare('INSERT INTO certificates (student_id, group_id, code, progress) VALUES (?,?,?,?)')
        ->execute([$studentId, $groupId, $code, $progress]);
    $st->execute([$studentId, $groupId]);
    return $st->fetch() ?: null;
}

function mail_user(array $user, string $subject, string $html): void
{
    $email = (string) ($user['email'] ?? '');
    if ($email === '' || !function_exists('send_mail')) {
        return;
    }
    $wrap = function_exists('mail_wrap') ? mail_wrap($subject, $html) : $html;
    send_mail($email, $subject, $wrap, strip_tags($html));
}

function academy_unique_slug(string $table, string $title, int $exceptId = 0): string
{
    $base = academy_slug($title);
    $slug = $base;
    $n = 2;
    $safe = preg_replace('/[^a-z0-9_]/', '', $table) ?: 'programs';
    while (true) {
        $sql = 'SELECT id FROM `' . $safe . '` WHERE slug = ?';
        $args = [$slug];
        if ($exceptId > 0) {
            $sql .= ' AND id <> ?';
            $args[] = $exceptId;
        }
        $st = db()->prepare($sql);
        $st->execute($args);
        if (!$st->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
}

function hoca_url(string $slug): string
{
    return rtrim(BASE_URL, '/') . '/hoca/' . rawurlencode($slug);
}

function teacher_public_slug(array $u): string
{
    $slug = trim((string) ($u['slug'] ?? ''));
    if ($slug !== '') {
        return $slug;
    }
    return academy_slug((string) ($u['name'] ?? 'hoca')) . '-' . (int) ($u['id'] ?? 0);
}

function academy_backfill_message_teachers(): void
{
    try {
        db()->exec(
            "UPDATE messages m
             SET teacher_id = (
               SELECT g.teacher_id FROM enrollments e
               JOIN class_groups g ON g.id = e.group_id
               WHERE e.student_id = m.thread_user_id
               LIMIT 1
             )
             WHERE teacher_id IS NULL"
        );
    } catch (Throwable) {
    }
}

function student_teachers(int $studentId): array
{
    $st = db()->prepare(
        "SELECT DISTINCT t.id, t.name, t.slug
         FROM enrollments e
         JOIN class_groups g ON g.id = e.group_id
         JOIN users t ON t.id = g.teacher_id
         WHERE e.student_id = ?
         ORDER BY t.name"
    );
    $st->execute([$studentId]);
    return $st->fetchAll();
}

function student_can_join_live(int $studentId, int $groupId): bool
{
    $st = db()->prepare(
        'SELECT p.access_type FROM enrollments e
         LEFT JOIN packages p ON p.id = e.package_id
         WHERE e.student_id = ? AND e.group_id = ?'
    );
    $st->execute([$studentId, $groupId]);
    $row = $st->fetch();
    if (!$row) {
        return false;
    }
    return ($row['access_type'] ?? 'canli_video') !== 'sadece_video';
}

function academy_gift_book(PDO $pdo, int $userId, int $bookId): void
{
    if ($bookId < 1) {
        return;
    }
    $chk = $pdo->prepare('SELECT id FROM student_books WHERE user_id = ? AND book_id = ?');
    $chk->execute([$userId, $bookId]);
    if ($chk->fetch()) {
        return;
    }
    $book = $pdo->prepare('SELECT id, is_digital FROM books WHERE id = ?');
    $book->execute([$bookId]);
    $b = $book->fetch();
    if (!$b) {
        return;
    }
    $digital = (int) ($b['is_digital'] ?? 0) === 1;
    $pdo->prepare('INSERT INTO student_books (user_id, book_id, status, kind) VALUES (?,?,?,?)')
        ->execute([
            $userId,
            $bookId,
            $digital ? 'İndirilebilir' : 'Hediye · hazırlanıyor',
            $digital ? 'Dijital PDF' : 'Hediye kitap',
        ]);
}

function academy_mimes_video(): array
{
    return [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];
}

function academy_mimes_doc(): array
{
    return [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function academy_can_access_group_file(array $user, int $groupId): bool
{
    $role = (string) ($user['role'] ?? '');
    if ($role === 'admin') {
        return true;
    }
    if ($role === 'ogretmen') {
        $st = db()->prepare('SELECT id FROM class_groups WHERE id = ? AND teacher_id = ?');
        $st->execute([$groupId, (int) $user['id']]);
        return (bool) $st->fetch();
    }
    if ($role === 'ogrenci') {
        return student_enrolled_group((int) $user['id'], $groupId);
    }
    return false;
}

function academy_tick_reminders(): void
{
    static $done = false;
    if ($done || empty($_SESSION['user_id'])) {
        return;
    }
    $done = true;
    $now = time();
    if (($now - (int) ($_SESSION['academy_tick'] ?? 0)) < 300) {
        return;
    }
    $_SESSION['academy_tick'] = $now;
    try {
        if (!function_exists('schedule_fetch') || !function_exists('schedule_now')) {
            return;
        }
        $from = schedule_now();
        $to = schedule_now()->modify('+20 minutes');
        $rows = schedule_fetch($from, $to);
        foreach ($rows as $ev) {
            $gid = (int) ($ev['group_id'] ?? 0);
            if ($gid < 1) {
                continue;
            }
            $when = substr((string) ($ev['starts_at'] ?? ''), 0, 16);
            $title = 'Ders yaklaşıyor';
            $body = (string) ($ev['title'] ?? 'Canlı ders') . ' · ' . $when;
            $chk = db()->prepare(
                'SELECT id FROM notifications WHERE title=? AND body=? LIMIT 1'
            );
            $chk->execute([$title, $body]);
            if ($chk->fetch()) {
                continue;
            }
            notify_group_students($gid, $title, $body, url('ogrenci/canli'));
            $stu = db()->prepare('SELECT u.* FROM enrollments e JOIN users u ON u.id=e.student_id WHERE e.group_id=?');
            $stu->execute([$gid]);
            foreach ($stu as $user) {
                mail_user($user, 'Ders hatırlatma', '<p>Canlı dersiniz yaklaşıyor: <b>' . e((string) ($ev['title'] ?? 'Ders')) . '</b></p>');
            }
        }
    } catch (Throwable) {
    }
}

function teacher_ogrenci_url(int $id): string
{
    return rtrim(BASE_URL, '/') . '/ogretmen/ogrenci/' . max(0, $id);
}

function teacher_owns_student(int $teacherId, int $studentId): bool
{
    $st = db()->prepare(
        "SELECT e.id FROM enrollments e
         JOIN class_groups g ON g.id = e.group_id
         WHERE g.teacher_id = ? AND e.student_id = ?
           AND (e.status IS NULL OR e.status <> 'silindi')
         LIMIT 1"
    );
    $st->execute([$teacherId, $studentId]);
    return (bool) $st->fetch();
}

function teacher_class_students(int $teacherId, ?int $groupId = null): array
{
    $sql = 'SELECT u.id, u.name, u.email, u.phone, u.city, u.avatar, u.status AS user_status,
                   e.id AS enroll_id, e.progress, e.status AS enroll_status, e.expires_at, e.started_at,
                   e.package_id, e.group_id, g.name AS group_name, g.days, p.title AS program_title,
                   pk.name AS package_name, pk.access_type
            FROM enrollments e
            JOIN users u ON u.id = e.student_id
            JOIN class_groups g ON g.id = e.group_id
            JOIN programs p ON p.id = g.program_id
            LEFT JOIN packages pk ON pk.id = e.package_id
            WHERE g.teacher_id = ? AND (e.status IS NULL OR e.status <> ?)
            ORDER BY g.name, u.name';
    $st = db()->prepare($sql);
    $st->execute([$teacherId, 'silindi']);
    $rows = $st->fetchAll();
    $gids = array_values(array_unique(array_map(static fn(array $r): int => (int) $r['group_id'], $rows)));
    $att = [];
    foreach ($gids as $gid) {
        $att[$gid] = group_last_attendance_map($gid);
    }
    $hwOpen = [];
    $ids = array_values(array_unique(array_map(static fn(array $r): int => (int) $r['id'], $rows)));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        try {
            $hs = db()->prepare(
                "SELECT s.student_id, h.group_id, COUNT(*) n
                 FROM homework_subs s
                 JOIN homework h ON h.id = s.homework_id
                 WHERE s.student_id IN ($in) AND s.status = 'open'
                 GROUP BY s.student_id, h.group_id"
            );
            $hs->execute($ids);
            foreach ($hs as $row) {
                $hwOpen[(int) $row['student_id'] . ':' . (int) $row['group_id']] = (int) $row['n'];
            }
        } catch (Throwable) {
        }
    }
    $out = [];
    foreach ($rows as $r) {
        if ($groupId && (int) $r['group_id'] !== $groupId) {
            continue;
        }
        $sid = (int) $r['id'];
        $gid = (int) $r['group_id'];
        $r['last_attendance'] = $att[$gid][$sid] ?? null;
        $r['hw_open'] = $hwOpen[$sid . ':' . $gid] ?? 0;
        $r['membership'] = enrollment_admin_state([
            'status' => $r['enroll_status'],
            'expires_at' => $r['expires_at'],
        ]);
        $out[] = $r;
    }
    return $out;
}

function enrollment_admin_state(array $en): array
{
    $status = (string) ($en['status'] ?? 'aktif');
    if ($status === 'pasif') {
        return [
            'expires' => $en['expires_at'] ?? null,
            'days' => null,
            'kind' => 'expired',
            'label' => 'pasif',
            'short' => 'Pasif',
        ];
    }
    return membership_from_expires(isset($en['expires_at']) ? (string) $en['expires_at'] : null);
}

function admin_save_user_status(int $userId, string $status): void
{
    if (!in_array($status, ['aktif', 'pasif', 'bekliyor'], true)) {
        throw new RuntimeException('Geçersiz hesap durumu.');
    }
    db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $userId]);
}

function admin_save_enrollment(int $enrollId, int $studentId, array $in): void
{
    $st = db()->prepare('SELECT * FROM enrollments WHERE id = ? AND student_id = ?');
    $st->execute([$enrollId, $studentId]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException('Kayıt bulunamadı.');
    }
    $status = ($in['status'] ?? '') === 'pasif' ? 'pasif' : 'aktif';
    $term = ($in['term'] ?? '') === 'suresiz' ? 'suresiz' : 'sureli';
    $pkg = max(0, (int) ($in['package_id'] ?? 0));
    $expires = null;
    if ($term === 'sureli') {
        $raw = trim((string) ($in['expires_at'] ?? ''));
        $ts = $raw !== '' ? strtotime($raw) : false;
        if ($ts === false) {
            throw new RuntimeException('Bitiş tarihi girin.');
        }
        $expires = date('Y-m-d 23:59:59', $ts);
        if ($status === 'aktif' && $ts < time()) {
            $status = 'suresi_doldu';
        }
    }
    db()->prepare('UPDATE enrollments SET status = ?, expires_at = ?, package_id = ? WHERE id = ? AND student_id = ?')
        ->execute([$status, $expires, $pkg > 0 ? $pkg : null, $enrollId, $studentId]);
}

function admin_add_enrollment(int $studentId, array $in): int
{
    $gid = (int) ($in['group_id'] ?? 0);
    $g = db()->prepare('SELECT id FROM class_groups WHERE id = ?');
    $g->execute([$gid]);
    if (!$g->fetch()) {
        throw new RuntimeException('Grup seçin.');
    }
    $chk = db()->prepare('SELECT id FROM enrollments WHERE student_id = ? AND group_id = ?');
    $chk->execute([$studentId, $gid]);
    $existing = $chk->fetch();
    if ($existing) {
        admin_save_enrollment((int) $existing['id'], $studentId, $in);
        return (int) $existing['id'];
    }
    $status = ($in['status'] ?? '') === 'pasif' ? 'pasif' : 'aktif';
    $term = ($in['term'] ?? '') === 'suresiz' ? 'suresiz' : 'sureli';
    $pkg = max(0, (int) ($in['package_id'] ?? 0));
    $expires = null;
    if ($term === 'sureli') {
        $raw = trim((string) ($in['expires_at'] ?? ''));
        $ts = $raw !== '' ? strtotime($raw) : false;
        if ($ts === false) {
            throw new RuntimeException('Bitiş tarihi girin.');
        }
        $expires = date('Y-m-d 23:59:59', $ts);
        if ($status === 'aktif' && $ts < time()) {
            $status = 'suresi_doldu';
        }
    }
    db()->prepare('INSERT INTO enrollments (student_id, group_id, progress, package_id, started_at, expires_at, status) VALUES (?,?,0,?,NOW(),?,?)')
        ->execute([$studentId, $gid, $pkg > 0 ? $pkg : null, $expires, $status]);
    return (int) db()->lastInsertId();
}

function academy_unread_count(int $userId): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}
