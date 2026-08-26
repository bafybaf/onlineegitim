<?php

function schedule_tz(): DateTimeZone
{
    return new DateTimeZone('Europe/Istanbul');
}

function schedule_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', schedule_tz());
}

function schedule_parse_day(?string $raw): DateTimeImmutable
{
    $tz = schedule_tz();
    $raw = trim((string) $raw);
    if ($raw !== '') {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $raw, $tz);
        if ($d instanceof DateTimeImmutable) {
            return $d->setTime(0, 0);
        }
    }
    return schedule_now()->setTime(0, 0);
}

function schedule_parse_datetime(string $raw): ?DateTimeImmutable
{
    $tz = schedule_tz();
    $raw = trim(str_replace('T', ' ', $raw));
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $fmt) {
        $d = DateTimeImmutable::createFromFormat($fmt, $raw, $tz);
        if ($d instanceof DateTimeImmutable) {
            return $d;
        }
    }
    return null;
}

function schedule_week_monday(DateTimeImmutable $d): DateTimeImmutable
{
    $n = (int) $d->format('N');
    return $d->modify('-' . ($n - 1) . ' days')->setTime(0, 0);
}

function schedule_month_first(DateTimeImmutable $d): DateTimeImmutable
{
    return $d->modify('first day of this month')->setTime(0, 0);
}

function schedule_ay_adi(int $m): string
{
    $aylar = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
    return $aylar[$m] ?? '';
}

function schedule_gun_kisa(int $n): string
{
    $gunler = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];
    return $gunler[$n] ?? '';
}

function schedule_status_label(string $status): string
{
    return match ($status) {
        'canli' => 'Canlı',
        'bitti' => 'Bitti',
        'iptal' => 'İptal',
        default => 'Planlandı',
    };
}

function enrollments_has_status_column(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    try {
        $row = db()->query("SHOW COLUMNS FROM enrollments LIKE 'status'")->fetch();
        $has = (bool) $row;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

function schedule_student_group_ids(int $studentId): array
{
    $sql = 'SELECT group_id FROM enrollments WHERE student_id = ?';
    if (enrollments_has_status_column()) {
        $sql .= " AND (status = 'aktif' OR status IS NULL)";
    }
    $st = db()->prepare($sql);
    $st->execute([$studentId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function schedule_teacher_groups(int $teacherId): array
{
    $st = db()->prepare('SELECT * FROM class_groups WHERE teacher_id = ? ORDER BY name');
    $st->execute([$teacherId]);
    return $st->fetchAll();
}

function schedule_all_groups(): array
{
    return db()->query('SELECT g.*, t.name teacher_name FROM class_groups g JOIN users t ON t.id = g.teacher_id ORDER BY g.name')->fetchAll();
}

function schedule_live_by_group(): array
{
    $rows = db()->query("SELECT * FROM live_rooms WHERE status = 'live' ORDER BY id")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['group_id']] = $r;
    }
    return $out;
}

function schedule_sync_statuses(): void
{
    $pdo = db();
    $pdo->exec(
        "UPDATE live_schedule
         SET status = 'bitti'
         WHERE status = 'planlandi'
           AND DATE_ADD(starts_at, INTERVAL duration_min MINUTE) < NOW()"
    );
    $pdo->exec(
        "UPDATE live_schedule s
         INNER JOIN live_rooms r ON r.group_id = s.group_id AND r.status = 'live'
         SET s.status = 'canli'
         WHERE s.status IN ('planlandi','canli')
           AND NOW() BETWEEN DATE_SUB(s.starts_at, INTERVAL 15 MINUTE)
                         AND DATE_ADD(s.starts_at, INTERVAL s.duration_min + 15 MINUTE)"
    );
    $pdo->exec(
        "UPDATE live_schedule s
         LEFT JOIN live_rooms r ON r.group_id = s.group_id AND r.status = 'live'
         SET s.status = 'bitti'
         WHERE s.status = 'canli'
           AND DATE_ADD(s.starts_at, INTERVAL s.duration_min MINUTE) < NOW()
           AND r.id IS NULL"
    );
}

function schedule_in_window(array $row, int $padMin = 15): bool
{
    $start = schedule_parse_datetime((string) $row['starts_at']);
    if (!$start) {
        return false;
    }
    $dur = max(1, (int) $row['duration_min']);
    $from = $start->modify('-' . $padMin . ' minutes');
    $to = $start->modify('+' . ($dur + $padMin) . ' minutes');
    $now = schedule_now();
    return $now >= $from && $now <= $to;
}

function schedule_display_status(array $row, ?array $liveRoom): string
{
    if (($row['status'] ?? '') === 'iptal') {
        return 'iptal';
    }
    if ($liveRoom) {
        return 'canli';
    }
    $start = schedule_parse_datetime((string) $row['starts_at']);
    if ($start) {
        $end = $start->modify('+' . max(1, (int) $row['duration_min']) . ' minutes');
        if (schedule_now() > $end) {
            return 'bitti';
        }
    }
    return ($row['status'] ?? '') === 'canli' ? 'canli' : 'planlandi';
}

function schedule_enrich(array $row, array $liveByGroup): array
{
    foreach (['title', 'topic', 'group_name', 'teacher_name', 'note'] as $k) {
        if (isset($row[$k]) && is_string($row[$k]) && function_exists('utf8_salvage')) {
            $row[$k] = utf8_salvage($row[$k]);
        }
    }
    $live = $liveByGroup[(int) $row['group_id']] ?? null;
    $row['live_room'] = $live;
    $row['display_status'] = schedule_display_status($row, $live);
    $row['in_window'] = ($row['status'] ?? '') !== 'iptal' && schedule_in_window($row);
    $row['can_join'] = $live && ($row['status'] ?? '') !== 'iptal';
    $row['can_open'] = ($row['status'] ?? '') !== 'iptal' && $row['in_window'];
    return $row;
}

function schedule_fetch(DateTimeImmutable $from, DateTimeImmutable $to, array $opts = []): array
{
    $sql = "SELECT s.*, g.name group_name, g.days group_days, t.name teacher_name
            FROM live_schedule s
            JOIN class_groups g ON g.id = s.group_id
            JOIN users t ON t.id = s.teacher_id
            WHERE s.starts_at >= ? AND s.starts_at < ?";
    $args = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];
    if (!empty($opts['teacher_id'])) {
        $sql .= ' AND s.teacher_id = ?';
        $args[] = (int) $opts['teacher_id'];
    }
    if (!empty($opts['group_ids']) && is_array($opts['group_ids'])) {
        $ids = array_values(array_filter(array_map('intval', $opts['group_ids'])));
        if (!$ids) {
            return [];
        }
        $sql .= ' AND s.group_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $args = array_merge($args, $ids);
    }
    $sql .= ' ORDER BY s.starts_at, s.id';
    $st = db()->prepare($sql);
    $st->execute($args);
    $live = schedule_live_by_group();
    $out = [];
    foreach ($st as $row) {
        $out[] = schedule_enrich($row, $live);
    }
    return $out;
}

function schedule_by_id(int $id): ?array
{
    $st = db()->prepare(
        "SELECT s.*, g.name group_name, g.days group_days, t.name teacher_name
         FROM live_schedule s
         JOIN class_groups g ON g.id = s.group_id
         JOIN users t ON t.id = s.teacher_id
         WHERE s.id = ?"
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    return schedule_enrich($row, schedule_live_by_group());
}

function schedule_group_row(int $groupId): ?array
{
    $st = db()->prepare('SELECT * FROM class_groups WHERE id = ?');
    $st->execute([$groupId]);
    return $st->fetch() ?: null;
}

function schedule_save(array $data): int
{
    $gid = (int) ($data['group_id'] ?? 0);
    $g = schedule_group_row($gid);
    if (!$g) {
        return 0;
    }
    $start = $data['starts_at'] ?? null;
    if (!$start instanceof DateTimeImmutable) {
        return 0;
    }
    $title = trim((string) ($data['title'] ?? '')) ?: (string) $g['name'];
    $topic = trim((string) ($data['topic'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));
    $dur = max(15, min(240, (int) ($data['duration_min'] ?? 60)));
    $id = (int) ($data['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('UPDATE live_schedule SET group_id=?, teacher_id=?, title=?, topic=?, starts_at=?, duration_min=?, note=? WHERE id=? AND status<>\'iptal\'')
            ->execute([$gid, (int) $g['teacher_id'], $title, $topic !== '' ? $topic : null, $start->format('Y-m-d H:i:s'), $dur, $note !== '' ? $note : null, $id]);
        return $id;
    }
    db()->prepare('INSERT INTO live_schedule (group_id, teacher_id, title, topic, starts_at, duration_min, status, note) VALUES (?,?,?,?,?,?,\'planlandi\',?)')
        ->execute([$gid, (int) $g['teacher_id'], $title, $topic !== '' ? $topic : null, $start->format('Y-m-d H:i:s'), $dur, $note !== '' ? $note : null]);
    return (int) db()->lastInsertId();
}

function schedule_delete(int $id, ?int $teacherId = null): bool
{
    $sql = 'DELETE FROM live_schedule WHERE id=?';
    $args = [$id];
    if ($teacherId) {
        $sql .= ' AND teacher_id=?';
        $args[] = $teacherId;
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->rowCount() > 0;
}

function schedule_cancel(int $id, ?int $teacherId = null): bool
{
    $sql = "UPDATE live_schedule SET status='iptal' WHERE id=? AND status<>'iptal'";
    $args = [$id];
    if ($teacherId) {
        $sql .= ' AND teacher_id=?';
        $args[] = $teacherId;
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->rowCount() > 0;
}

function schedule_group_by_day(array $sessions): array
{
    $out = [];
    foreach ($sessions as $row) {
        $d = substr((string) $row['starts_at'], 0, 10);
        $out[$d][] = $row;
    }
    return $out;
}

function schedule_input_value(string $startsAt): string
{
    $d = schedule_parse_datetime($startsAt);
    return $d ? $d->format('Y-m-d\TH:i') : '';
}

function schedule_badge(string $status): string
{
    $label = schedule_status_label($status);
    $cls = match ($status) {
        'canli' => 'bg-red-100 text-red-700',
        'bitti' => 'bg-slate-100 text-slate-600',
        'iptal' => 'bg-slate-100 text-slate-400 line-through',
        default => 'bg-indigo-50 text-navy',
    };
    return '<span class="rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide ' . $cls . '">' . e($label) . '</span>';
}

function schedule_nav_url(string $base, string $mode, DateTimeImmutable $day, int $editId = 0): string
{
    $q = ['gorunum' => $mode, 't' => $day->format('Y-m-d')];
    if ($editId > 0) {
        $q['duzenle'] = $editId;
    }
    return url($base . '?' . http_build_query($q));
}

function schedule_actions(array $row, string $role, string $pageBase): string
{
    $html = '';
    if (!empty($row['can_join']) && $row['live_room']) {
        $html .= '<a class="btn-primary h-8 px-3 text-xs" href="' . e(canli_url((int) $row['live_room']['id'])) . '">' . ($role === 'ogrenci' ? 'Katıl' : 'Sınıfa gir') . '</a>';
    } elseif ($role === 'ogretmen' && !empty($row['can_open'])) {
        $html .= '<form method="post" action="' . e(url('api/live.php')) . '" class="inline">'
            . '<input type="hidden" name="action" value="start">'
            . '<input type="hidden" name="html" value="1">'
            . '<input type="hidden" name="group_id" value="' . (int) $row['group_id'] . '">'
            . '<input type="hidden" name="topic" value="' . e((string) ($row['topic'] ?: $row['title'])) . '">'
            . '<input type="hidden" name="record" value="1">'
            . '<input type="hidden" name="yoklama" value="1">'
            . live_play_mode_picker('play_mode', live_last_play_mode(), 'hidden')
            . '<button class="btn-primary h-8 px-3 text-xs">Odayı aç</button></form>';
    }
    if (in_array($role, ['ogretmen', 'admin'], true) && ($row['status'] ?? '') !== 'iptal' && ($row['display_status'] ?? '') !== 'bitti') {
        $html .= '<a class="btn-outline h-8 px-3 text-xs" href="' . e(schedule_nav_url($pageBase, $_GET['gorunum'] ?? 'hafta', schedule_parse_day($_GET['t'] ?? null), (int) $row['id'])) . '">Düzenle</a>';
        $html .= '<form method="post" class="inline" onsubmit="return confirm(\'Bu ders saati iptal edilsin mi?\');">'
            . '<input type="hidden" name="action" value="cancel">'
            . '<input type="hidden" name="id" value="' . (int) $row['id'] . '">'
            . '<button class="btn-outline h-8 px-3 text-xs">İptal</button></form>';
    }
    if (in_array($role, ['ogretmen', 'admin'], true)) {
        $html .= '<form method="post" class="inline" onsubmit="return confirm(\'Bu ders saati silinsin mi?\');">'
            . '<input type="hidden" name="action" value="delete">'
            . '<input type="hidden" name="id" value="' . (int) $row['id'] . '">'
            . '<button class="btn-outline h-8 px-3 text-xs">Sil</button></form>';
    }
    return $html;
}

function schedule_card(array $row, string $role, string $pageBase): string
{
    $start = schedule_parse_datetime((string) $row['starts_at']);
    $saat = $start ? $start->format('H:i') : '';
    $cls = ($row['display_status'] ?? '') === 'iptal' ? 'opacity-50' : '';
    $html = '<div class="rounded-xl bg-soft p-2 ' . $cls . '">';
    $html .= '<p class="text-xs font-extrabold">' . e($saat) . ' · ' . (int) $row['duration_min'] . ' dk</p>';
    $html .= '<p class="text-sm font-bold leading-snug">' . e((string) $row['title']) . '</p>';
    if (!empty($row['topic'])) {
        $html .= '<p class="text-xs text-muted">' . e((string) $row['topic']) . '</p>';
    }
    if ($role === 'admin' || $role === 'ogrenci') {
        $html .= '<p class="text-[11px] text-muted">' . e((string) $row['teacher_name']) . '</p>';
    }
    $html .= '<div class="mt-1">' . schedule_badge((string) $row['display_status']) . '</div>';
    $acts = schedule_actions($row, $role, $pageBase);
    if ($acts !== '') {
        $html .= '<div class="mt-2 flex flex-wrap gap-1">' . $acts . '</div>';
    }
    $html .= '</div>';
    return $html;
}

function schedule_render_week(array $sessions, DateTimeImmutable $monday, string $role, string $pageBase): void
{
    $byDay = schedule_group_by_day($sessions);
    $today = schedule_now()->format('Y-m-d');
    echo '<div class="overflow-x-auto"><div class="grid min-w-[920px] grid-cols-7 gap-2">';
    for ($i = 0; $i < 7; $i++) {
        $day = $monday->modify('+' . $i . ' days');
        $key = $day->format('Y-m-d');
        $isToday = $key === $today;
        echo '<div class="min-h-[12rem] rounded-2xl border bg-white p-3 ' . ($isToday ? 'border-navy shadow-sm' : 'border-[#e5e5e7]') . '">';
        echo '<p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-muted">' . e(schedule_gun_kisa((int) $day->format('N'))) . '</p>';
        echo '<p class="font-display text-xl' . ($isToday ? ' text-navy' : '') . '">' . e($day->format('j')) . '</p>';
        foreach ($byDay[$key] ?? [] as $row) {
            echo '<div class="mt-2">' . schedule_card($row, $role, $pageBase) . '</div>';
        }
        echo '</div>';
    }
    echo '</div></div>';
}

function schedule_render_month(array $sessions, DateTimeImmutable $monthFirst, string $role, string $pageBase): void
{
    $byDay = schedule_group_by_day($sessions);
    $today = schedule_now()->format('Y-m-d');
    $startPad = (int) $monthFirst->format('N') - 1;
    $daysInMonth = (int) $monthFirst->format('t');
    echo '<div class="overflow-x-auto"><div class="grid min-w-[920px] grid-cols-7 gap-1">';
    for ($i = 1; $i <= 7; $i++) {
        echo '<p class="px-2 pb-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-muted">' . e(schedule_gun_kisa($i)) . '</p>';
    }
    for ($i = 0; $i < $startPad; $i++) {
        echo '<div class="min-h-[7rem] rounded-xl bg-transparent"></div>';
    }
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $day = $monthFirst->setDate((int) $monthFirst->format('Y'), (int) $monthFirst->format('n'), $d);
        $key = $day->format('Y-m-d');
        $isToday = $key === $today;
        echo '<div class="min-h-[7rem] rounded-xl border bg-white p-2 ' . ($isToday ? 'border-navy' : 'border-[#e5e5e7]') . '">';
        echo '<p class="text-sm font-extrabold' . ($isToday ? ' text-navy' : '') . '">' . $d . '</p>';
        foreach ($byDay[$key] ?? [] as $row) {
            echo '<div class="mt-1">' . schedule_card($row, $role, $pageBase) . '</div>';
        }
        echo '</div>';
    }
    echo '</div></div>';
}

function schedule_handle_post(array $u, bool $admin): string
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return '';
    }
    $action = post('action');
    $teacherId = $admin ? null : (int) $u['id'];
    if ($action === 'cancel') {
        return schedule_cancel((int) post('id'), $teacherId) ? 'Ders saati iptal edildi.' : 'İptal edilemedi.';
    }
    if ($action === 'delete') {
        return schedule_delete((int) post('id'), $teacherId) ? 'Ders saati silindi.' : 'Silinemedi.';
    }
    if ($action !== 'create' && $action !== 'update') {
        return '';
    }
    $gid = (int) post('group_id');
    $g = schedule_group_row($gid);
    if (!$g) {
        return 'Grup bulunamadı.';
    }
    if (!$admin && (int) $g['teacher_id'] !== (int) $u['id']) {
        return 'Bu grup size ait değil.';
    }
    $start = schedule_parse_datetime(post('starts_at'));
    if (!$start) {
        return 'Tarih ve saat geçersiz.';
    }
    $id = $action === 'update' ? (int) post('id') : 0;
    if ($id > 0) {
        $ex = schedule_by_id($id);
        if (!$ex || (!$admin && (int) $ex['teacher_id'] !== (int) $u['id'])) {
            return 'Kayıt bulunamadı.';
        }
    }
    $ok = schedule_save([
        'id' => $id,
        'group_id' => $gid,
        'title' => post('title'),
        'topic' => post('topic'),
        'note' => post('note'),
        'starts_at' => $start,
        'duration_min' => (int) post('duration_min', '60'),
    ]);
    if (!$ok) {
        return 'Kaydedilemedi.';
    }
    return $id > 0 ? 'Ders saati güncellendi.' : 'Ders saati eklendi.';
}

function schedule_form(array $groups, ?array $edit, string $submitLabel): void
{
    $gid = (int) ($edit['group_id'] ?? 0);
    ?>
  <form method="post" class="card grid gap-3 p-5 md:grid-cols-2">
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <label class="text-sm font-bold">Grup
      <select name="group_id" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= $gid === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?><?= !empty($g['teacher_name']) ? ' · ' . e($g['teacher_name']) : '' ?> · <?= e((string) $g['days']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm font-bold">Başlık
      <input name="title" required maxlength="160" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($edit['title'] ?? '')) ?>" placeholder="Örn. Tefsir A">
    </label>
    <label class="text-sm font-bold">Konu
      <input name="topic" maxlength="160" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($edit['topic'] ?? '')) ?>" placeholder="Bu seansın konusu">
    </label>
    <label class="text-sm font-bold">Tarih ve saat
      <input type="datetime-local" name="starts_at" required class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e($edit ? schedule_input_value((string) $edit['starts_at']) : '') ?>">
    </label>
    <label class="text-sm font-bold">Süre (dk)
      <input type="number" name="duration_min" min="15" max="240" step="15" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= (int) ($edit['duration_min'] ?? 60) ?>">
    </label>
    <label class="text-sm font-bold">Not (isteğe bağlı)
      <input name="note" maxlength="255" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal" value="<?= e((string) ($edit['note'] ?? '')) ?>" placeholder="Örn. her Pazartesi">
    </label>
    <div class="md:col-span-2 flex flex-wrap gap-2">
      <button class="btn-primary"><?= e($submitLabel) ?></button>
      <?php if ($edit): ?><a class="btn-outline" href="?">Vazgeç</a><?php endif; ?>
    </div>
  </form>
    <?php if ($edit): ?>
  <form method="post" class="mt-3" onsubmit="return confirm('Bu ders saati silinsin mi?');">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <button class="btn-outline">Ders saatini sil</button>
  </form>
    <?php endif; ?>
    <?php
}

function schedule_toolbar(string $pageBase, string $mode, DateTimeImmutable $cursor, DateTimeImmutable $prev, DateTimeImmutable $next, string $title): void
{
    ?>
  <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
      <p class="text-xs font-extrabold uppercase tracking-[0.16em] text-muted">Canlı ders takvimi</p>
      <h2 class="font-display text-2xl"><?= e($title) ?></h2>
    </div>
    <div class="flex flex-wrap gap-2">
      <a class="btn-outline h-10 px-3 text-sm" href="<?= e(schedule_nav_url($pageBase, $mode, $prev)) ?>">← Önceki</a>
      <a class="btn-outline h-10 px-3 text-sm" href="<?= e(schedule_nav_url($pageBase, $mode, schedule_now())) ?>">Bugün</a>
      <a class="btn-outline h-10 px-3 text-sm" href="<?= e(schedule_nav_url($pageBase, $mode, $next)) ?>">Sonraki →</a>
      <a class="h-10 px-3 text-sm <?= $mode === 'hafta' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(schedule_nav_url($pageBase, 'hafta', $cursor)) ?>">Hafta</a>
      <a class="h-10 px-3 text-sm <?= $mode === 'ay' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(schedule_nav_url($pageBase, 'ay', $cursor)) ?>">Ay</a>
    </div>
  </div>
    <?php
}
