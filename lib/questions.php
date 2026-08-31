<?php
declare(strict_types=1);

function ensure_questions_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS student_questions (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              student_id INT UNSIGNED NULL,
              teacher_id INT UNSIGNED NULL,
              group_id INT UNSIGNED NULL,
              program_id INT UNSIGNED NULL,
              guest_name VARCHAR(120) NULL,
              guest_email VARCHAR(160) NULL,
              source VARCHAR(20) NOT NULL DEFAULT 'panel',
              body TEXT NOT NULL,
              answer TEXT NULL,
              answered_at DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_q_teacher (teacher_id, answered_at, id),
              KEY idx_q_student (student_id, id),
              KEY idx_q_program (program_id, answered_at, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable) {
    }
    $cols = [];
    try {
        foreach (db()->query('SHOW COLUMNS FROM student_questions')->fetchAll() as $row) {
            $cols[(string) $row['Field']] = $row;
        }
    } catch (Throwable) {
        return;
    }
    $alters = [];
    if (isset($cols['student_id']) && strtoupper((string) ($cols['student_id']['Null'] ?? '')) === 'NO') {
        $alters[] = 'MODIFY student_id INT UNSIGNED NULL';
    }
    if (isset($cols['teacher_id']) && strtoupper((string) ($cols['teacher_id']['Null'] ?? '')) === 'NO') {
        $alters[] = 'MODIFY teacher_id INT UNSIGNED NULL';
    }
    if (!isset($cols['program_id'])) {
        $alters[] = 'ADD COLUMN program_id INT UNSIGNED NULL AFTER group_id';
    }
    if (!isset($cols['guest_name'])) {
        $alters[] = 'ADD COLUMN guest_name VARCHAR(120) NULL AFTER program_id';
    }
    if (!isset($cols['guest_email'])) {
        $alters[] = 'ADD COLUMN guest_email VARCHAR(160) NULL AFTER guest_name';
    }
    if (!isset($cols['source'])) {
        $alters[] = "ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'panel' AFTER guest_email";
    }
    if ($alters) {
        try {
            db()->exec('ALTER TABLE student_questions ' . implode(', ', $alters));
        } catch (Throwable) {
        }
    }
    try {
        db()->exec('CREATE INDEX idx_q_program ON student_questions (program_id, answered_at, id)');
    } catch (Throwable) {
    }
}

function question_student_groups(int $studentId): array
{
    $st = db()->prepare(
        "SELECT g.id, g.name, t.id teacher_id, t.name teacher_name
         FROM enrollments e
         JOIN class_groups g ON g.id = e.group_id
         JOIN users t ON t.id = g.teacher_id
         WHERE e.student_id = ?
           AND (e.status = 'aktif' OR e.status IS NULL)
           AND (e.expires_at IS NULL OR e.expires_at > NOW())
         ORDER BY g.name"
    );
    $st->execute([$studentId]);
    return $st->fetchAll();
}

function question_open_count(int $studentId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM student_questions WHERE student_id = ? AND answered_at IS NULL');
    $st->execute([$studentId]);
    return (int) $st->fetchColumn();
}

function question_program_teacher_ids(int $programId): array
{
    if ($programId < 1) {
        return [];
    }
    $st = db()->prepare('SELECT DISTINCT teacher_id FROM class_groups WHERE program_id = ?');
    $st->execute([$programId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function question_admin_ids(): array
{
    try {
        return array_map('intval', db()->query(
            "SELECT id FROM users WHERE role = 'admin' AND status IN ('aktif','bekliyor')"
        )->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable) {
        return [];
    }
}

function question_teacher_pending_count(int $teacherId): int
{
    try {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM student_questions
             WHERE answered_at IS NULL
               AND (teacher_id = ? OR program_id IN (SELECT program_id FROM class_groups WHERE teacher_id = ?))'
        );
        $st->execute([$teacherId, $teacherId]);
        return (int) $st->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function question_admin_pending_count(): int
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM student_questions WHERE answered_at IS NULL')->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function question_create(int $studentId, int $groupId, string $body): int
{
    $body = trim($body);
    if (mb_strlen($body) < 8) {
        throw new RuntimeException('Sorunuzu biraz daha ayrıntılı yazın.');
    }
    if (mb_strlen($body) > 2000) {
        throw new RuntimeException('Soru en fazla 2000 karakter olabilir.');
    }
    if (question_open_count($studentId) >= 8) {
        throw new RuntimeException('Bekleyen 8 sorunuz var. Hocanız yanıtlayınca yenisini yazabilirsiniz.');
    }
    $groups = question_student_groups($studentId);
    $row = null;
    foreach ($groups as $g) {
        if ((int) $g['id'] === $groupId) {
            $row = $g;
            break;
        }
    }
    if (!$row) {
        throw new RuntimeException('Bu ders için soru soramazsınız.');
    }
    $teacherId = (int) $row['teacher_id'];
    db()->prepare('INSERT INTO student_questions (student_id, teacher_id, group_id, body, source) VALUES (?,?,?,?,?)')
        ->execute([$studentId, $teacherId, $groupId, $body, 'panel']);
    $id = (int) db()->lastInsertId();
    $who = (string) (current_user()['name'] ?? 'Öğrenci');
    notify_user(
        $teacherId,
        'Yeni soru',
        $who . ': ' . mb_strimwidth($body, 0, 80, '…'),
        url('ogretmen/sorular.php?id=' . $id)
    );
    foreach (question_admin_ids() as $aid) {
        if ($aid !== $teacherId) {
            notify_user($aid, 'Yeni soru', $who . ': ' . mb_strimwidth($body, 0, 80, '…'), url('admin/sorular.php?id=' . $id));
        }
    }
    return $id;
}

function program_ask_create(int $programId, string $name, string $email, string $body, ?int $userId = null): int
{
    $name = mb_substr(trim($name), 0, 120);
    $email = mb_strtolower(trim($email));
    $body = trim($body);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ad ve geçerli e-posta girin.');
    }
    if (mb_strlen($body) < 8) {
        throw new RuntimeException('Sorunuzu biraz daha ayrıntılı yazın.');
    }
    if (mb_strlen($body) > 2000) {
        throw new RuntimeException('Soru en fazla 2000 karakter olabilir.');
    }
    $st = db()->prepare('SELECT id, title FROM programs WHERE id = ?');
    $st->execute([$programId]);
    $prog = $st->fetch();
    if (!$prog) {
        throw new RuntimeException('Program bulunamadı.');
    }
    $teachers = question_program_teacher_ids($programId);
    $teacherId = $teachers[0] ?? null;
    $studentId = null;
    if ($userId && $userId > 0) {
        $u = db()->prepare('SELECT id, role FROM users WHERE id = ?');
        $u->execute([$userId]);
        $row = $u->fetch();
        if ($row && ($row['role'] ?? '') === 'ogrenci') {
            $studentId = (int) $row['id'];
        }
    }
    db()->prepare(
        'INSERT INTO student_questions (student_id, teacher_id, program_id, guest_name, guest_email, source, body)
         VALUES (?,?,?,?,?,?,?)'
    )->execute([$studentId, $teacherId, $programId, $name, $email, 'program', $body]);
    $id = (int) db()->lastInsertId();
    $snip = $name . ': ' . mb_strimwidth($body, 0, 80, '…');
    $title = 'Program sorusu · ' . (string) $prog['title'];
    $seen = [];
    foreach ($teachers as $tid) {
        $seen[$tid] = true;
        notify_user($tid, $title, $snip, url('ogretmen/sorular.php?id=' . $id));
    }
    foreach (question_admin_ids() as $aid) {
        if (isset($seen[$aid])) {
            continue;
        }
        notify_user($aid, $title, $snip, url('admin/sorular.php?id=' . $id));
    }
    $html = mail_wrap($title, '<p><b>Ad:</b> ' . e($name) . '<br><b>E-posta:</b> ' . e($email) . '<br><b>Program:</b> ' . e((string) $prog['title']) . '</p><p>' . nl2br(e($body)) . '</p>');
    notify_admin($title, $html, $name . "\n" . $email . "\n" . $body, $email);
    return $id;
}

function question_can_answer(array $user, array $row): bool
{
    $role = (string) ($user['role'] ?? '');
    if ($role === 'admin') {
        return true;
    }
    if ($role !== 'ogretmen') {
        return false;
    }
    $tid = (int) ($user['id'] ?? 0);
    if ($tid > 0 && (int) ($row['teacher_id'] ?? 0) === $tid) {
        return true;
    }
    $pid = (int) ($row['program_id'] ?? 0);
    if ($pid < 1 || $tid < 1) {
        return false;
    }
    $st = db()->prepare('SELECT 1 FROM class_groups WHERE program_id = ? AND teacher_id = ? LIMIT 1');
    $st->execute([$pid, $tid]);
    return (bool) $st->fetch();
}

function question_answer(array $user, int $questionId, string $answer): void
{
    $answer = trim($answer);
    if (mb_strlen($answer) < 2) {
        throw new RuntimeException('Yanıt yazın.');
    }
    if (mb_strlen($answer) > 4000) {
        throw new RuntimeException('Yanıt en fazla 4000 karakter olabilir.');
    }
    $st = db()->prepare('SELECT * FROM student_questions WHERE id = ?');
    $st->execute([$questionId]);
    $row = $st->fetch();
    if (!$row || !question_can_answer($user, $row)) {
        throw new RuntimeException('Soru bulunamadı.');
    }
    db()->prepare('UPDATE student_questions SET answer = ?, answered_at = NOW() WHERE id = ?')
        ->execute([$answer, $questionId]);
    $sid = (int) ($row['student_id'] ?? 0);
    if ($sid > 0) {
        notify_user(
            $sid,
            'Sorunuza yanıt verildi',
            mb_strimwidth($answer, 0, 80, '…'),
            url('ogrenci/soru-sor.php?id=' . $questionId)
        );
    }
    $mailTo = trim((string) ($row['guest_email'] ?? ''));
    if ($mailTo !== '' && filter_var($mailTo, FILTER_VALIDATE_EMAIL) && function_exists('send_mail')) {
        $html = mail_wrap('Sorunuza yanıt', '<p>' . nl2br(e($answer)) . '</p>');
        send_mail($mailTo, 'Sorunuza yanıt · Online İlahiyat', $html, $answer);
    }
}

function question_row_name(array $q): string
{
    $n = trim((string) ($q['sname'] ?? ''));
    if ($n !== '') {
        return $n;
    }
    $n = trim((string) ($q['guest_name'] ?? ''));
    return $n !== '' ? $n : 'Ziyaretçi';
}

function question_row_email(array $q): string
{
    $e = trim((string) ($q['semail'] ?? ''));
    if ($e !== '') {
        return $e;
    }
    return trim((string) ($q['guest_email'] ?? ''));
}

function question_row_context(array $q): string
{
    if (!empty($q['gname'])) {
        return (string) $q['gname'];
    }
    if (!empty($q['pname'])) {
        return (string) $q['pname'];
    }
    return 'Soru';
}
