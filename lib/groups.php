<?php

function class_groups_columns(bool $reload = false): array
{
    static $cols = null;
    if ($reload) {
        $cols = null;
    }
    if (is_array($cols)) {
        return $cols;
    }
    $cols = [];
    try {
        foreach (db()->query('SHOW COLUMNS FROM class_groups')->fetchAll() as $row) {
            $cols[(string) $row['Field']] = true;
        }
    } catch (Throwable) {
        $cols = [];
    }
    return $cols;
}

function ensure_class_groups_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $cols = class_groups_columns();
    if (!isset($cols['description'])) {
        try {
            $after = isset($cols['days']) ? ' AFTER days' : '';
            db()->exec('ALTER TABLE class_groups ADD COLUMN description TEXT NULL' . $after);
            class_groups_columns(true);
        } catch (Throwable) {
            // Sütun zaten varsa veya yetki yoksa sessizce geç.
        }
    }
    $cols = class_groups_columns();
    if (!isset($cols['whatsapp_url'])) {
        try {
            db()->exec('ALTER TABLE class_groups ADD COLUMN whatsapp_url VARCHAR(255) NULL');
            class_groups_columns(true);
        } catch (Throwable) {
        }
    }
    ensure_group_teachers_schema();
}

function ensure_group_teachers_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS class_group_teachers (
                group_id INT UNSIGNED NOT NULL,
                teacher_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (group_id, teacher_id),
                KEY idx_cgt_teacher (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->exec(
            'INSERT IGNORE INTO class_group_teachers (group_id, teacher_id)
             SELECT id, teacher_id FROM class_groups WHERE teacher_id IS NOT NULL AND teacher_id > 0'
        );
    } catch (Throwable) {
    }
}

function group_owned_ids(int $teacherId): array
{
    ensure_group_teachers_schema();
    $ids = [];
    try {
        $st = db()->prepare('SELECT group_id FROM class_group_teachers WHERE teacher_id = ?');
        $st->execute([$teacherId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable) {
    }
    try {
        $st = db()->prepare('SELECT id FROM class_groups WHERE teacher_id = ?');
        $st->execute([$teacherId]);
        foreach ($st as $row) {
            $ids[] = (int) $row['id'];
        }
    } catch (Throwable) {
    }
    return array_values(array_unique(array_filter($ids)));
}

function group_owned_in_sql(int $teacherId): string
{
    $ids = group_owned_ids($teacherId);
    return $ids ? implode(',', $ids) : '0';
}

function group_has_teacher(int $groupId, int $teacherId): bool
{
    if ($groupId < 1 || $teacherId < 1) {
        return false;
    }
    return in_array($groupId, group_owned_ids($teacherId), true);
}

function group_teacher_ids(int $groupId): array
{
    ensure_group_teachers_schema();
    try {
        $st = db()->prepare('SELECT teacher_id FROM class_group_teachers WHERE group_id = ? ORDER BY teacher_id');
        $st->execute([$groupId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        if ($ids) {
            return $ids;
        }
    } catch (Throwable) {
    }
    $st = db()->prepare('SELECT teacher_id FROM class_groups WHERE id = ?');
    $st->execute([$groupId]);
    $one = (int) $st->fetchColumn();
    return $one > 0 ? [$one] : [];
}

function group_teachers_label_map(array $groupIds): array
{
    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));
    if (!$groupIds) {
        return [];
    }
    ensure_group_teachers_schema();
    $in = implode(',', $groupIds);
    $map = [];
    try {
        $rows = db()->query(
            "SELECT cgt.group_id, u.name
             FROM class_group_teachers cgt
             JOIN users u ON u.id = cgt.teacher_id
             WHERE cgt.group_id IN ($in)
             ORDER BY u.name"
        )->fetchAll();
        foreach ($rows as $row) {
            $gid = (int) $row['group_id'];
            $map[$gid][] = (string) $row['name'];
        }
    } catch (Throwable) {
    }
    foreach ($map as $gid => $names) {
        $map[$gid] = implode(', ', $names);
    }
    return $map;
}

function group_apply_teacher_labels(array $rows, string $idKey = 'id'): array
{
    $map = group_teachers_label_map(array_map(static fn(array $r): int => (int) ($r[$idKey] ?? 0), $rows));
    foreach ($rows as &$r) {
        $gid = (int) ($r[$idKey] ?? 0);
        if ($gid > 0 && isset($map[$gid]) && $map[$gid] !== '') {
            $r['teacher_name'] = $map[$gid];
            $r['teacher_names'] = $map[$gid];
            if (array_key_exists('teacher', $r)) {
                $r['teacher'] = $map[$gid];
            }
        }
    }
    unset($r);
    return $rows;
}

function group_posted_teacher_ids(): array
{
    $raw = $_POST['teacher_ids'] ?? $_POST['teacher_id'] ?? [];
    if (!is_array($raw)) {
        $raw = $raw !== '' && $raw !== null ? [$raw] : [];
    }
    $ids = [];
    foreach ($raw as $v) {
        $id = (int) $v;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function group_normalize_teacher_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT id FROM users WHERE role = 'ogretmen' AND id IN ($in)");
    $st->execute($ids);
    $ok = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $order = array_flip($ids);
    usort($ok, static fn(int $a, int $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
    return $ok;
}

function group_set_teachers(int $groupId, array $ids, ?int $preferPrimary = null): void
{
    ensure_group_teachers_schema();
    $ids = group_normalize_teacher_ids($ids);
    if ($ids === []) {
        throw new RuntimeException('En az bir hoca seçin.');
    }
    if ($preferPrimary && in_array($preferPrimary, $ids, true)) {
        $ids = array_values(array_unique(array_merge([$preferPrimary], $ids)));
    }
    db()->prepare('DELETE FROM class_group_teachers WHERE group_id = ?')->execute([$groupId]);
    $ins = db()->prepare('INSERT INTO class_group_teachers (group_id, teacher_id) VALUES (?,?)');
    foreach ($ids as $tid) {
        $ins->execute([$groupId, $tid]);
    }
    db()->prepare('UPDATE class_groups SET teacher_id = ? WHERE id = ?')->execute([$ids[0], $groupId]);
}

function group_teachers_field(array $teachers, array $selectedIds, string $hint = '', ?int $lockedId = null): string
{
    $selected = array_map('intval', $selectedIds);
    $lockedId = $lockedId !== null ? (int) $lockedId : 0;
    $html = '<div class="md:col-span-2">';
    $html .= '<p class="text-sm font-bold">Hocalar</p>';
    $html .= '<p class="mt-1 text-xs font-normal text-muted">' . e($hint !== '' ? $hint : 'Birden fazla hoca seçebilirsiniz. Ctrl gerekmez; kutuları işaretleyin.') . '</p>';
    $html .= '<div class="mt-2 grid gap-2 sm:grid-cols-2">';
    foreach ($teachers as $t) {
        $id = (int) $t['id'];
        $on = in_array($id, $selected, true) || ($lockedId > 0 && $id === $lockedId);
        $lock = $lockedId > 0 && $id === $lockedId;
        $html .= '<label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-normal">';
        if ($lock) {
            $html .= '<input type="hidden" name="teacher_ids[]" value="' . $id . '">';
            $html .= '<input type="checkbox" checked disabled>';
        } else {
            $html .= '<input type="checkbox" name="teacher_ids[]" value="' . $id . '"' . ($on ? ' checked' : '') . '>';
        }
        $html .= e((string) $t['name']);
        $html .= '</label>';
    }
    $html .= '</div></div>';
    return $html;
}

function grup_url(int $id): string
{
    return rtrim(BASE_URL, '/') . '/admin/grup/' . max(0, $id);
}

function ogretmen_grup_url(int $id): string
{
    return rtrim(BASE_URL, '/') . '/ogretmen/grup/' . max(0, $id);
}

function groups_notice(?string $msg = null): string
{
    if ($msg !== null) {
        $_SESSION['groups_ok'] = $msg;
        return '';
    }
    $m = (string) ($_SESSION['groups_ok'] ?? '');
    unset($_SESSION['groups_ok']);
    return $m;
}

function groups_error(?string $msg = null): string
{
    if ($msg !== null) {
        $_SESSION['groups_err'] = $msg;
        return '';
    }
    $m = (string) ($_SESSION['groups_err'] ?? '');
    unset($_SESSION['groups_err']);
    return $m;
}

function group_enroll_cols(): array
{
    return function_exists('live_enrollment_columns') ? live_enrollment_columns() : [];
}

function group_cap_html(int $n, int $cap): string
{
    $cap = max(1, $cap);
    $cls = $n >= $cap ? 'mem-bad' : ($n >= $cap - 1 ? 'text-amber-700' : 'mem-ok');
    return '<span class="' . $cls . ' font-extrabold">' . $n . ' / ' . $cap . '</span>';
}

function group_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $st = db()->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);
        $cache[$table] = (bool) $st->fetchColumn();
    } catch (Throwable) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function group_programs(): array
{
    return db()->query('SELECT id, title FROM programs ORDER BY title')->fetchAll();
}

function group_teachers(): array
{
    return db()->query("SELECT id, name FROM users WHERE role = 'ogretmen' ORDER BY name")->fetchAll();
}

function group_by_id(int $id, ?int $teacherId = null): ?array
{
    $sql = 'SELECT g.*, p.title AS program_title, t.name AS teacher_name, t.email AS teacher_email, t.phone AS teacher_phone
            FROM class_groups g
            JOIN programs p ON p.id = g.program_id
            JOIN users t ON t.id = g.teacher_id
            WHERE g.id = ?';
    $args = [$id];
    if ($teacherId !== null) {
        $sql .= ' AND (g.teacher_id = ? OR EXISTS (SELECT 1 FROM class_group_teachers cgt WHERE cgt.group_id = g.id AND cgt.teacher_id = ?))';
        $args[] = $teacherId;
        $args[] = $teacherId;
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    $row['teacher_ids'] = group_teacher_ids((int) $row['id']);
    $labels = group_teachers_label_map([(int) $row['id']]);
    if (!empty($labels[(int) $row['id']])) {
        $row['teacher_name'] = $labels[(int) $row['id']];
        $row['teacher_names'] = $labels[(int) $row['id']];
    }
    return $row;
}

function group_list(?int $teacherId = null): array
{
    $sql = 'SELECT g.*, p.title AS program_title, t.name AS teacher_name,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.group_id = g.id) AS n
            FROM class_groups g
            JOIN programs p ON p.id = g.program_id
            JOIN users t ON t.id = g.teacher_id';
    $args = [];
    if ($teacherId !== null) {
        $sql .= ' WHERE (g.teacher_id = ? OR EXISTS (SELECT 1 FROM class_group_teachers cgt WHERE cgt.group_id = g.id AND cgt.teacher_id = ?))';
        $args[] = $teacherId;
        $args[] = $teacherId;
    }
    $sql .= ' ORDER BY ' . catalog_order_sql('g', 'class_groups');
    $st = db()->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();
    if (!$rows) {
        return [];
    }
    $ids = array_map(static fn(array $r): int => (int) $r['id'], $rows);
    $counts = group_side_counts($ids);
    $next = group_next_sessions($ids);
    $live = function_exists('schedule_live_by_group') ? schedule_live_by_group() : [];
    foreach ($rows as &$r) {
        $gid = (int) $r['id'];
        $r['n'] = (int) $r['n'];
        $r['hw_n'] = $counts[$gid]['hw'] ?? 0;
        $r['test_n'] = $counts[$gid]['test'] ?? 0;
        $r['live_room'] = $live[$gid] ?? null;
        $r['next_session'] = $next[$gid] ?? null;
    }
    unset($r);
    return group_apply_teacher_labels($rows);
}

function group_side_counts(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $out = [];
    foreach ($ids as $id) {
        $out[$id] = ['hw' => 0, 'test' => 0];
    }
    if (!$ids) {
        return $out;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    if (group_table_exists('homework')) {
        $st = db()->prepare("SELECT group_id, COUNT(*) n FROM homework WHERE group_id IN ($in) GROUP BY group_id");
        $st->execute($ids);
        foreach ($st as $row) {
            $out[(int) $row['group_id']]['hw'] = (int) $row['n'];
        }
    }
    if (group_table_exists('tests')) {
        $st = db()->prepare("SELECT group_id, COUNT(*) n FROM tests WHERE group_id IN ($in) GROUP BY group_id");
        $st->execute($ids);
        foreach ($st as $row) {
            $out[(int) $row['group_id']]['test'] = (int) $row['n'];
        }
    }
    return $out;
}

function group_next_sessions(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids || !group_table_exists('live_schedule')) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(
        "SELECT s.group_id, s.title, s.topic, s.starts_at, s.duration_min
         FROM live_schedule s
         INNER JOIN (
           SELECT group_id, MIN(starts_at) AS nxt
           FROM live_schedule
           WHERE group_id IN ($in) AND starts_at >= NOW() AND status <> 'iptal'
           GROUP BY group_id
         ) x ON x.group_id = s.group_id AND x.nxt = s.starts_at
         WHERE s.status <> 'iptal'"
    );
    $st->execute($ids);
    $out = [];
    foreach ($st as $row) {
        $out[(int) $row['group_id']] = $row;
    }
    return $out;
}

function group_roster(int $groupId): array
{
    $eCols = group_enroll_cols();
    $uCols = function_exists('users_columns') ? users_columns() : [];
    $select = ['e.student_id', 'e.group_id', 'u.name', 'u.email', 'u.role'];
    if (isset($eCols['progress'])) {
        $select[] = 'e.progress';
    }
    if (isset($eCols['started_at'])) {
        $select[] = 'e.started_at';
    }
    if (isset($eCols['expires_at'])) {
        $select[] = 'e.expires_at';
    }
    if (isset($eCols['package_id'])) {
        $select[] = 'e.package_id';
    }
    if (isset($uCols['phone'])) {
        $select[] = 'u.phone';
    }
    if (isset($uCols['city'])) {
        $select[] = 'u.city';
    }
    if (isset($uCols['avatar'])) {
        $select[] = 'u.avatar';
    }
    if (isset($uCols['membership_expires_at'])) {
        $select[] = 'u.membership_expires_at';
    }
    $sql = 'SELECT ' . implode(', ', $select) . '
            FROM enrollments e
            JOIN users u ON u.id = e.student_id
            WHERE e.group_id = ?
            ORDER BY u.name';
    $st = db()->prepare($sql);
    $st->execute([$groupId]);
    $rows = $st->fetchAll();
    $att = group_last_attendance_map($groupId);
    foreach ($rows as &$r) {
        $sid = (int) $r['student_id'];
        $r['last_attendance'] = $att[$sid] ?? null;
        $exp = null;
        if (isset($eCols['expires_at']) && !empty($r['expires_at'])) {
            $exp = (string) $r['expires_at'];
        } elseif (isset($uCols['membership_expires_at']) && !empty($r['membership_expires_at'])) {
            $exp = (string) $r['membership_expires_at'];
        }
        $r['membership'] = function_exists('membership_from_expires')
            ? membership_from_expires($exp)
            : ['label' => '—', 'kind' => 'unknown', 'days' => null];
        $r['progress'] = (int) ($r['progress'] ?? 0);
    }
    unset($r);
    return $rows;
}

function group_last_attendance_map(int $groupId): array
{
    if (!group_table_exists('attendance') || !group_table_exists('live_rooms')) {
        return [];
    }
    try {
        $st = db()->prepare(
            'SELECT a.student_id,
                    MAX(r.started_at) AS last_at,
                    MAX(CASE WHEN a.present = 1 THEN r.started_at END) AS last_present
             FROM attendance a
             JOIN live_rooms r ON r.id = a.room_id
             WHERE r.group_id = ?
             GROUP BY a.student_id'
        );
        $st->execute([$groupId]);
        $out = [];
        foreach ($st as $row) {
            $out[(int) $row['student_id']] = $row;
        }
        return $out;
    } catch (Throwable) {
        return [];
    }
}

function group_upcoming(int $groupId, int $days = 21): array
{
    if (!function_exists('schedule_fetch') || !group_table_exists('live_schedule')) {
        return [];
    }
    try {
        if (function_exists('schedule_sync_statuses')) {
            schedule_sync_statuses();
        }
        $from = schedule_now();
        $to = $from->modify('+' . max(1, $days) . ' days');
        return schedule_fetch($from, $to, ['group_ids' => [$groupId]]);
    } catch (Throwable) {
        return [];
    }
}

function group_live_rooms(int $groupId): array
{
    if (!group_table_exists('live_rooms')) {
        return [];
    }
    $st = db()->prepare('SELECT * FROM live_rooms WHERE group_id = ? ORDER BY id DESC LIMIT 12');
    $st->execute([$groupId]);
    return $st->fetchAll();
}

function group_counts(int $groupId): array
{
    $side = group_side_counts([$groupId]);
    $n = 0;
    $st = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE group_id = ?');
    $st->execute([$groupId]);
    $n = (int) $st->fetchColumn();
    return [
        'n' => $n,
        'hw' => $side[$groupId]['hw'] ?? 0,
        'test' => $side[$groupId]['test'] ?? 0,
    ];
}

function group_students_available(int $groupId): array
{
    $st = db()->prepare(
        "SELECT u.id, u.name, u.email, u.phone
         FROM users u
         WHERE u.role = 'ogrenci'
           AND u.id NOT IN (SELECT e.student_id FROM enrollments e WHERE e.group_id = ?)
         ORDER BY u.name"
    );
    $st->execute([$groupId]);
    return $st->fetchAll();
}

function group_normalize(array $in, bool $admin): array
{
    $name = trim((string) ($in['name'] ?? ''));
    $days = trim((string) ($in['days'] ?? ''));
    $desc = trim((string) ($in['description'] ?? ''));
    $cap = (int) ($in['cap'] ?? 10);
    $out = [
        'name' => mb_substr($name, 0, 80),
        'days' => mb_substr($days, 0, 80),
        'description' => $desc !== '' ? mb_substr($desc, 0, 4000) : null,
        'whatsapp_url' => function_exists('academy_normalize_wa')
            ? academy_normalize_wa((string) ($in['whatsapp_url'] ?? ''))
            : (trim((string) ($in['whatsapp_url'] ?? '')) ?: null),
        'cap' => max(1, min(80, $cap)),
    ];
    if ($admin) {
        $out['program_id'] = (int) ($in['program_id'] ?? 0);
        $out['teacher_id'] = (int) ($in['teacher_id'] ?? 0);
    }
    if (isset($in['teacher_ids']) && is_array($in['teacher_ids'])) {
        $out['teacher_ids'] = group_normalize_teacher_ids($in['teacher_ids']);
        if ($out['teacher_ids'] !== []) {
            $out['teacher_id'] = (int) $out['teacher_ids'][0];
        }
    }
    return $out;
}

function group_validate(array $data, bool $admin): string
{
    if ($data['name'] === '' || mb_strlen($data['name']) < 2) {
        return 'Grup adı en az 2 karakter olmalı.';
    }
    if ($data['days'] === '') {
        return 'Ders günleri boş olamaz.';
    }
    if ($admin) {
        $p = db()->prepare('SELECT id FROM programs WHERE id = ?');
        $p->execute([(int) $data['program_id']]);
        if (!$p->fetch()) {
            return 'Program seçin.';
        }
        $ids = group_normalize_teacher_ids($data['teacher_ids'] ?? [(int) ($data['teacher_id'] ?? 0)]);
        if ($ids === []) {
            return 'En az bir hoca seçin.';
        }
    }
    return '';
}

function group_save(array $data, int $id = 0, bool $admin = true): int
{
    $cols = class_groups_columns();
    if ($id > 0) {
        if ($admin) {
            $sql = 'UPDATE class_groups SET name = ?, days = ?, cap = ?, program_id = ?, teacher_id = ?';
            $args = [$data['name'], $data['days'], $data['cap'], $data['program_id'], $data['teacher_id']];
        } else {
            $sql = 'UPDATE class_groups SET name = ?, days = ?, cap = ?';
            $args = [$data['name'], $data['days'], $data['cap']];
            if ((int) ($data['program_id'] ?? 0) > 0) {
                $sql .= ', program_id = ?';
                $args[] = (int) $data['program_id'];
            }
        }
        if (isset($cols['description'])) {
            $sql .= ', description = ?';
            $args[] = $data['description'];
        }
        if (isset($cols['whatsapp_url'])) {
            $sql .= ', whatsapp_url = ?';
            $args[] = $data['whatsapp_url'] ?? null;
        }
        $sql .= ' WHERE id = ?';
        $args[] = $id;
        db()->prepare($sql)->execute($args);
        group_save_teachers($id, $data);
        return $id;
    }
    $fields = ['program_id', 'teacher_id', 'name', 'days', 'cap'];
    $vals = [$data['program_id'], $data['teacher_id'], $data['name'], $data['days'], $data['cap']];
    if (isset($cols['description'])) {
        $fields[] = 'description';
        $vals[] = $data['description'];
    }
    if (isset($cols['whatsapp_url'])) {
        $fields[] = 'whatsapp_url';
        $vals[] = $data['whatsapp_url'] ?? null;
    }
    $sql = 'INSERT INTO class_groups (' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')';
    db()->prepare($sql)->execute($vals);
    $id = (int) db()->lastInsertId();
    group_save_teachers($id, $data);
    return $id;
}

function group_save_teachers(int $id, array $data): void
{
    $ids = $data['teacher_ids'] ?? [];
    if (!is_array($ids) || $ids === []) {
        $one = (int) ($data['teacher_id'] ?? 0);
        $ids = $one > 0 ? [$one] : [];
    }
    if ($ids === []) {
        return;
    }
    $prefer = (int) ($data['teacher_id'] ?? 0);
    group_set_teachers($id, $ids, $prefer > 0 ? $prefer : null);
}

function group_add_student(int $groupId, int $studentId): string
{
    $g = group_by_id($groupId);
    if (!$g) {
        return 'Grup bulunamadı.';
    }
    $u = db()->prepare("SELECT id, role FROM users WHERE id = ?");
    $u->execute([$studentId]);
    $stu = $u->fetch();
    if (!$stu || ($stu['role'] ?? '') !== 'ogrenci') {
        return 'Geçerli bir öğrenci seçin.';
    }
    $chk = db()->prepare('SELECT id FROM enrollments WHERE student_id = ? AND group_id = ?');
    $chk->execute([$studentId, $groupId]);
    if ($chk->fetch()) {
        return 'Bu öğrenci zaten bu grupta.';
    }
    $st = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE group_id = ?');
    $st->execute([$groupId]);
    $n = (int) $st->fetchColumn();
    $cap = max(1, (int) $g['cap']);
    if ($n >= $cap) {
        return 'Kontenjan dolu (' . $n . ' / ' . $cap . ').';
    }
    $cols = group_enroll_cols();
    $fields = ['student_id', 'group_id'];
    $vals = [$studentId, $groupId];
    if (isset($cols['progress'])) {
        $fields[] = 'progress';
        $vals[] = 0;
    }
    if (isset($cols['started_at'])) {
        $fields[] = 'started_at';
        $vals[] = date('Y-m-d H:i:s');
    }
    if (isset($cols['status'])) {
        $fields[] = 'status';
        $vals[] = 'aktif';
    }
    $sql = 'INSERT INTO enrollments (' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')';
    db()->prepare($sql)->execute($vals);
    return '';
}

function group_delete(int $id): void
{
    $g = group_by_id($id);
    if (!$g) {
        throw new RuntimeException('Grup bulunamadı.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_try_exec('UPDATE packages SET default_group_id = NULL WHERE default_group_id = ?', [$id]);
        db_try_exec('UPDATE payments SET group_id = NULL WHERE group_id = ?', [$id]);
        db_try_exec('DELETE FROM enrollments WHERE group_id = ?', [$id]);
        if (group_table_exists('homework_subs') && group_table_exists('homework')) {
            $hw = $pdo->prepare('SELECT id FROM homework WHERE group_id = ?');
            $hw->execute([$id]);
            $files = $pdo->prepare('SELECT file_path FROM homework_subs WHERE homework_id = ?');
            foreach ($hw as $row) {
                $files->execute([(int) $row['id']]);
                foreach ($files as $f) {
                    if (function_exists('academy_unlink_stored')) {
                        academy_unlink_stored($f['file_path'] ?? null);
                    }
                }
            }
            $pdo->prepare('DELETE FROM homework WHERE group_id = ?')->execute([$id]);
        }
        if (group_table_exists('tests')) {
            $pdo->prepare('DELETE FROM tests WHERE group_id = ?')->execute([$id]);
        }
        if (group_table_exists('live_schedule')) {
            $pdo->prepare('DELETE FROM live_schedule WHERE group_id = ?')->execute([$id]);
        }
        if (group_table_exists('live_rooms')) {
            $pdo->prepare('DELETE FROM live_rooms WHERE group_id = ?')->execute([$id]);
        }
        if (group_table_exists('recordings')) {
            $st = $pdo->prepare('SELECT video_path FROM recordings WHERE group_id = ?');
            $st->execute([$id]);
            foreach ($st as $row) {
                if (function_exists('academy_unlink_stored')) {
                    academy_unlink_stored($row['video_path'] ?? null);
                }
            }
            $pdo->prepare('DELETE FROM recordings WHERE group_id = ?')->execute([$id]);
        }
        if (group_table_exists('lesson_notes')) {
            $st = $pdo->prepare('SELECT file_path FROM lesson_notes WHERE group_id = ?');
            $st->execute([$id]);
            foreach ($st as $row) {
                if (function_exists('academy_unlink_stored')) {
                    academy_unlink_stored($row['file_path'] ?? null);
                }
            }
            $pdo->prepare('DELETE FROM lesson_notes WHERE group_id = ?')->execute([$id]);
        }
        db_try_exec('DELETE FROM certificates WHERE group_id = ?', [$id]);
        db_try_exec('UPDATE student_questions SET group_id = NULL WHERE group_id = ?', [$id]);
        db_try_exec('DELETE FROM class_group_teachers WHERE group_id = ?', [$id]);
        $pdo->prepare('DELETE FROM class_groups WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException('Grup silinemedi. Bağlı kayıtlar var olabilir.');
    }
}

function group_remove_student(int $groupId, int $studentId): string
{
    $st = db()->prepare('DELETE FROM enrollments WHERE student_id = ? AND group_id = ?');
    $st->execute([$studentId, $groupId]);
    return $st->rowCount() > 0 ? '' : 'Kayıt bulunamadı.';
}

function group_handle_admin_post(int $id = 0): int
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return $id;
    }
    $action = post('action');
    if ($action === 'create' || $action === 'save') {
        $admin = true;
        $data = group_normalize([
            'name' => post('name'),
            'days' => post('days'),
            'description' => post('description'),
            'whatsapp_url' => post('whatsapp_url'),
            'cap' => post('cap'),
            'program_id' => post('program_id'),
            'teacher_id' => post('teacher_id'),
            'teacher_ids' => group_posted_teacher_ids(),
        ], $admin);
        $err = group_validate($data, $admin);
        if ($err !== '') {
            groups_error($err);
            return $id;
        }
        $saved = group_save($data, $action === 'save' ? $id : 0, true);
        groups_notice($action === 'save' ? 'Grup güncellendi.' : 'Grup oluşturuldu.');
        redirect(grup_url($saved));
    }
    if ($id < 1) {
        return $id;
    }
    if ($action === 'delete') {
        try {
            group_delete($id);
            groups_notice('Grup silindi.');
            redirect('admin/gruplar');
        } catch (Throwable $e) {
            groups_error($e->getMessage());
            redirect(grup_url($id));
        }
    }
    if ($action === 'add_student') {
        $err = group_add_student($id, (int) post('student_id'));
        if ($err !== '') {
            groups_error($err);
        } else {
            groups_notice('Öğrenci gruba eklendi.');
        }
        redirect(grup_url($id));
    }
    if ($action === 'remove_student') {
        $err = group_remove_student($id, (int) post('student_id'));
        if ($err !== '') {
            groups_error($err);
        } else {
            groups_notice('Öğrenci gruptan çıkarıldı.');
        }
        redirect(grup_url($id));
    }
    return $id;
}

function group_validate_teacher(array $data): string
{
    $err = group_validate($data, false);
    if ($err !== '') {
        return $err;
    }
    $p = db()->prepare('SELECT id FROM programs WHERE id = ?');
    $p->execute([(int) ($data['program_id'] ?? 0)]);
    if (!$p->fetch()) {
        return 'Program seçin.';
    }
    return '';
}

function group_teacher_payload(int $teacherId): array
{
    $data = group_normalize([
        'name' => post('name'),
        'days' => post('days'),
        'description' => post('description'),
        'whatsapp_url' => post('whatsapp_url'),
        'cap' => post('cap'),
    ], false);
    $data['program_id'] = (int) post('program_id');
    $data['teacher_id'] = $teacherId;
    $ids = group_posted_teacher_ids();
    $ids[] = $teacherId;
    $data['teacher_ids'] = $ids;
    return $data;
}

function group_handle_teacher_post(int $id, int $teacherId): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $action = post('action');
    if ($action === 'create') {
        $data = group_teacher_payload($teacherId);
        $err = group_validate_teacher($data);
        if ($err !== '') {
            groups_error($err);
            redirect('ogretmen/siniflar');
        }
        $saved = group_save($data, 0, false);
        groups_notice('Grup oluşturuldu.');
        redirect(ogretmen_grup_url($saved));
    }
    if ($id < 1) {
        return;
    }
    $g = group_by_id($id, $teacherId);
    if (!$g) {
        groups_error('Bu grup size ait değil.');
        redirect('ogretmen/siniflar');
    }
    if ($action === 'delete') {
        try {
            group_delete($id);
            groups_notice('Grup silindi.');
            redirect('ogretmen/siniflar');
        } catch (Throwable $e) {
            groups_error($e->getMessage());
            redirect(ogretmen_grup_url($id));
        }
    }
    if ($action !== 'save') {
        return;
    }
    $data = group_teacher_payload($teacherId);
    $err = group_validate_teacher($data);
    if ($err !== '') {
        groups_error($err);
        redirect(ogretmen_grup_url($id));
    }
    group_save($data, $id, false);
    groups_notice('Sınıf bilgileri güncellendi.');
    redirect(ogretmen_grup_url($id));
}

function group_flash_html(): void
{
    $ok = groups_notice();
    $err = groups_error();
    if ($ok !== '') {
        echo '<p class="mb-4 font-bold text-green-700">' . e($ok) . '</p>';
    }
    if ($err !== '') {
        echo '<p class="mb-4 font-bold text-red-700">' . e($err) . '</p>';
    }
}

function group_attendance_label(?array $att): string
{
    if (!$att) {
        return '—';
    }
    $raw = (string) ($att['last_present'] ?: $att['last_at'] ?? '');
    if ($raw === '') {
        return '—';
    }
    $label = function_exists('profile_dt') ? profile_dt($raw) : $raw;
    if (!empty($att['last_present'])) {
        return $label;
    }
    return $label . ' (yok)';
}
