<?php
function teacher_owns_group(int $teacherId, int $groupId): bool
{
    $st = db()->prepare('SELECT id FROM class_groups WHERE id=? AND teacher_id=?');
    $st->execute([$groupId, $teacherId]);
    return (bool) $st->fetch();
}

function teacher_test(int $testId, int $teacherId): ?array
{
    $st = db()->prepare('SELECT t.*, g.name gname FROM tests t JOIN class_groups g ON g.id=t.group_id WHERE t.id=? AND t.teacher_id=? AND g.teacher_id=?');
    $st->execute([$testId, $teacherId, $teacherId]);
    return $st->fetch() ?: null;
}

function student_published_test(int $testId, int $studentId): ?array
{
    $st = db()->prepare("SELECT t.*, g.name gname, te.name teacher_name
      FROM tests t
      JOIN class_groups g ON g.id=t.group_id
      JOIN users te ON te.id=t.teacher_id
      JOIN enrollments e ON e.group_id=t.group_id AND e.student_id=?
      WHERE t.id=? AND t.status='yayinda'");
    $st->execute([$studentId, $testId]);
    return $st->fetch() ?: null;
}

function test_questions(int $testId, bool $withCorrect = false): array
{
    $cols = $withCorrect
        ? 'id, test_id, body, choice_a, choice_b, choice_c, choice_d, correct, points, sort_order'
        : 'id, test_id, body, choice_a, choice_b, choice_c, choice_d, points, sort_order';
    $st = db()->prepare("SELECT $cols FROM test_questions WHERE test_id=? ORDER BY sort_order, id");
    $st->execute([$testId]);
    return $st->fetchAll();
}

function test_attempt(int $testId, int $studentId): ?array
{
    $st = db()->prepare('SELECT * FROM test_attempts WHERE test_id=? AND student_id=? AND submitted_at IS NOT NULL');
    $st->execute([$testId, $studentId]);
    return $st->fetch() ?: null;
}

function test_max_points(int $testId): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(points),0) FROM test_questions WHERE test_id=?');
    $st->execute([$testId]);
    return (int) $st->fetchColumn();
}

function test_choice_keys(): array
{
    return ['a', 'b', 'c', 'd'];
}

function test_choice_label(string $key): string
{
    return strtoupper($key);
}

function test_percent(int $score, int $max): int
{
    if ($max < 1) {
        return 0;
    }
    return (int) round(($score / $max) * 100);
}
