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
              student_id INT UNSIGNED NOT NULL,
              teacher_id INT UNSIGNED NOT NULL,
              group_id INT UNSIGNED NULL,
              body TEXT NOT NULL,
              answer TEXT NULL,
              answered_at DATETIME NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_q_teacher (teacher_id, answered_at, id),
              KEY idx_q_student (student_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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

function question_teacher_pending_count(int $teacherId): int
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM student_questions WHERE teacher_id = ? AND answered_at IS NULL');
        $st->execute([$teacherId]);
        return (int) $st->fetchColumn();
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
    db()->prepare('INSERT INTO student_questions (student_id, teacher_id, group_id, body) VALUES (?,?,?,?)')
        ->execute([$studentId, $teacherId, $groupId, $body]);
    $id = (int) db()->lastInsertId();
    $who = (string) (current_user()['name'] ?? 'Öğrenci');
    notify_user(
        $teacherId,
        'Yeni soru',
        $who . ': ' . mb_strimwidth($body, 0, 80, '…'),
        url('ogretmen/sorular.php?id=' . $id)
    );
    return $id;
}

function question_answer(int $teacherId, int $questionId, string $answer): void
{
    $answer = trim($answer);
    if (mb_strlen($answer) < 2) {
        throw new RuntimeException('Yanıt yazın.');
    }
    if (mb_strlen($answer) > 4000) {
        throw new RuntimeException('Yanıt en fazla 4000 karakter olabilir.');
    }
    $st = db()->prepare('SELECT * FROM student_questions WHERE id = ? AND teacher_id = ?');
    $st->execute([$questionId, $teacherId]);
    $row = $st->fetch();
    if (!$row) {
        throw new RuntimeException('Soru bulunamadı.');
    }
    db()->prepare('UPDATE student_questions SET answer = ?, answered_at = NOW() WHERE id = ? AND teacher_id = ?')
        ->execute([$answer, $questionId, $teacherId]);
    notify_user(
        (int) $row['student_id'],
        'Hocanız sorunuza yanıt verdi',
        mb_strimwidth($answer, 0, 80, '…'),
        url('ogrenci/soru-sor.php?id=' . $questionId)
    );
}
