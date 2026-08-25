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
        $sql .= ' AND g.teacher_id = ?';
        $args[] = $teacherId;
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    $row = $st->fetch();
    return $row ?: null;
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
        $sql .= ' WHERE g.teacher_id = ?';
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
    return $rows;
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
        $t = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'ogretmen'");
        $t->execute([(int) $data['teacher_id']]);
        if (!$t->fetch()) {
            return 'Hoca seçin.';
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
    return (int) db()->lastInsertId();
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

function group_handle_teacher_post(int $id, int $teacherId): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    if (post('action') !== 'save') {
        return;
    }
    $g = group_by_id($id, $teacherId);
    if (!$g) {
        groups_error('Bu grup size ait değil.');
        redirect(url('ogretmen/siniflar'));
    }
    $data = group_normalize([
        'name' => post('name'),
        'days' => post('days'),
        'description' => post('description'),
        'whatsapp_url' => post('whatsapp_url'),
        'cap' => post('cap'),
    ], false);
    $err = group_validate($data, false);
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
