<?php

function users_columns(bool $reload = false): array
{
    static $cols = null;
    if ($reload) {
        $cols = null;
    }
    if (is_array($cols)) {
        return $cols;
    }
    $cols = [];
    foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $row) {
        $cols[(string) $row['Field']] = true;
    }
    return $cols;
}

function ensure_user_profile_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $cols = [];
    foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $row) {
        $cols[(string) $row['Field']] = true;
    }
    if (!isset($cols['membership_expires_at'])) {
        db()->exec('ALTER TABLE users ADD COLUMN membership_expires_at DATETIME NULL AFTER status');
    }
    if (!isset($cols['avatar'])) {
        $after = isset($cols['bio']) ? ' AFTER bio' : '';
        db()->exec('ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL' . $after);
        $cols['avatar'] = true;
    }
    if (!isset($cols['google_id'])) {
        db()->exec('ALTER TABLE users ADD COLUMN google_id VARCHAR(64) NULL');
        try {
            db()->exec('CREATE UNIQUE INDEX uq_users_google ON users (google_id)');
        } catch (Throwable) {
            // İndeks zaten varsa sessizce geç.
        }
        users_columns(true);
    }
}

function ensure_shop_account_model(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("UPDATE packages SET active = 0 WHERE kind = 'magaza'");
        db()->exec("UPDATE users SET status = 'aktif' WHERE role = 'musteri' AND status = 'bekliyor'");
    } catch (Throwable) {
        // Şema yoksa atlanır.
    }
    foreach (['google_enabled' => '0', 'google_client_id' => '', 'google_client_secret' => ''] as $k => $v) {
        if (setting($k, '__missing__') === '__missing__') {
            try {
                setting_set($k, $v);
            } catch (Throwable) {
            }
        }
    }
}

function user_role_label(string $role): string
{
    return match ($role) {
        'ogrenci' => 'Öğrenci',
        'ogretmen' => 'Hoca',
        'admin' => 'Yönetici',
        'musteri' => 'Müşteri',
        default => $role,
    };
}

function user_status_label(string $status): string
{
    return match ($status) {
        'aktif' => 'Aktif',
        'pasif' => 'Pasif',
        'bekliyor' => 'Bekliyor',
        default => $status,
    };
}

function user_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    $first = mb_substr((string) ($parts[0] ?? ''), 0, 1, 'UTF-8');
    $last = count($parts) > 1 ? mb_substr((string) $parts[count($parts) - 1], 0, 1, 'UTF-8') : '';
    $out = mb_strtoupper($first . $last, 'UTF-8');
    return $out !== '' ? $out : '?';
}

function user_avatar_src(?string $avatar): string
{
    return '';
}

function user_can_view_avatar(array $subject, bool $ownPreview = false): bool
{
    $viewer = function_exists('current_user') ? current_user() : null;
    if (!$viewer) {
        return false;
    }
    if (($viewer['role'] ?? '') === 'admin') {
        return true;
    }
    return $ownPreview && (int) ($viewer['id'] ?? 0) === (int) ($subject['id'] ?? 0);
}

function user_avatar_url(array $u): string
{
    $avatar = trim((string) ($u['avatar'] ?? ''));
    $id = (int) ($u['id'] ?? $u['student_id'] ?? 0);
    if ($avatar === '' || $id < 1) {
        return '';
    }
    return rtrim(BASE_URL, '/') . '/api/dosya.php?tur=avatar&id=' . $id;
}

function user_avatar_html(array $u, string $size = 'md', bool $ownPreview = false): string
{
    $src = user_can_view_avatar($u, $ownPreview) ? user_avatar_url($u) : '';
    $cls = 'avatar avatar-' . (in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md');
    $alt = (string) ($u['name'] ?? '');
    if ($src !== '') {
        return '<span class="' . $cls . '"><img src="' . e($src) . '" alt="' . e($alt) . '"></span>';
    }
    return '<span class="' . $cls . '" aria-hidden="true">' . e(user_initials($alt)) . '</span>';
}

function user_avatar_file_abs(string $avatar): ?string
{
    $avatar = trim(str_replace('\\', '/', $avatar));
    if ($avatar === '' || preg_match('#^https?://#i', $avatar)) {
        return null;
    }
    $avatar = ltrim($avatar, '/');
    $ext = strtolower((string) pathinfo($avatar, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return null;
    }
    if (str_starts_with($avatar, 'avatars/')) {
        $abs = function_exists('academy_file_readable') ? academy_file_readable($avatar) : null;
        return $abs ?: null;
    }
    if (str_starts_with($avatar, 'uploads/avatars/')) {
        if (function_exists('media_upload_file_abs')) {
            $p = media_upload_file_abs($avatar);
            if ($p !== '' && is_file($p)) {
                return $p;
            }
        }
        $p = dirname(__DIR__) . '/' . $avatar;
        return is_file($p) ? $p : null;
    }
    return null;
}

function user_delete_avatar_file(string $rel): void
{
    $abs = user_avatar_file_abs($rel);
    if ($abs && is_file($abs)) {
        @unlink($abs);
    }
}

function user_clear_avatar(int $userId, string $oldPath = ''): void
{
    if ($oldPath === '') {
        $st = db()->prepare('SELECT avatar FROM users WHERE id = ?');
        $st->execute([$userId]);
        $oldPath = (string) ($st->fetchColumn() ?: '');
    }
    user_delete_avatar_file($oldPath);
    db()->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$userId]);
}

function user_store_avatar_upload(int $userId, string $oldPath = ''): ?string
{
    if ($userId < 1 || !function_exists('academy_store_upload')) {
        return null;
    }
    $path = academy_store_upload('avatar', 'avatars', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ], 4);
    if ($path === null) {
        return null;
    }
    if ($oldPath === '') {
        $st = db()->prepare('SELECT avatar FROM users WHERE id = ?');
        $st->execute([$userId]);
        $oldPath = (string) ($st->fetchColumn() ?: '');
    }
    user_delete_avatar_file($oldPath);
    db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$path, $userId]);
    return $path;
}

function profile_dt(?string $dt): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    $t = strtotime($dt);
    return $t ? date('d.m.Y H:i', $t) : $dt;
}

function membership_from_expires(?string $expiresAt): array
{
    $expiresAt = is_string($expiresAt) && trim($expiresAt) !== '' ? $expiresAt : null;
    if ($expiresAt === null) {
        return [
            'expires' => null,
            'days' => null,
            'kind' => 'unlimited',
            'label' => 'süresiz',
            'short' => 'Süresiz',
        ];
    }
    $ts = strtotime($expiresAt);
    if ($ts === false) {
        return [
            'expires' => $expiresAt,
            'days' => null,
            'kind' => 'unknown',
            'label' => '—',
            'short' => '—',
        ];
    }
    $days = (int) ceil(($ts - time()) / 86400);
    if ($days <= 0) {
        return [
            'expires' => $expiresAt,
            'days' => 0,
            'kind' => 'expired',
            'label' => 'süresi doldu',
            'short' => 'Süresi doldu',
        ];
    }
    return [
        'expires' => $expiresAt,
        'days' => $days,
        'kind' => 'active',
        'label' => $days . ' gün kaldı',
        'short' => $days . ' gün kaldı',
    ];
}

function live_membership_expires(array $u, ?array $enrolls = null): ?string
{
    $role = (string) ($u['role'] ?? '');
    if ($role === 'ogrenci') {
        $rows = $enrolls ?? user_enrollments((int) $u['id']);
        $latest = null;
        foreach ($rows as $row) {
            $exp = $row['expires_at'] ?? null;
            if ($exp === null || $exp === '') {
                return null;
            }
            if ($latest === null || strtotime((string) $exp) > strtotime((string) $latest)) {
                $latest = $exp;
            }
        }
        if ($latest !== null) {
            return (string) $latest;
        }
    }
    $own = $u['membership_expires_at'] ?? null;
    return is_string($own) && $own !== '' ? $own : null;
}

function live_membership_state(array $u, ?array $enrolls = null): array
{
    $role = (string) ($u['role'] ?? '');
    if (in_array($role, ['admin', 'ogretmen'], true)) {
        return [
            'expires' => null,
            'days' => null,
            'kind' => 'staff',
            'label' => 'süresiz',
            'short' => 'Süresiz',
        ];
    }
    $hasEnroll = $role === 'ogrenci' && ($enrolls ?? user_enrollments((int) $u['id']));
    $exp = live_membership_expires($u, $enrolls);
    if ($exp === null && $role === 'ogrenci' && !$hasEnroll) {
        $own = $u['membership_expires_at'] ?? null;
        if (!is_string($own) || $own === '') {
            return [
                'expires' => null,
                'days' => 0,
                'kind' => 'none',
                'label' => 'süresi doldu',
                'short' => 'Süresi doldu',
            ];
        }
    }
    return membership_from_expires($exp);
}

function membership_kind_class(string $kind): string
{
    return match ($kind) {
        'active' => 'mem-ok',
        'expired', 'none' => 'mem-bad',
        'unlimited', 'staff' => 'mem-soft',
        default => 'text-muted',
    };
}

function hoca_admin_url(int $id): string
{
    if ($id < 1) {
        return rtrim(BASE_URL, '/') . '/admin/hoca/yeni';
    }
    return rtrim(BASE_URL, '/') . '/admin/hoca/' . $id;
}

function kullanici_url(int $id): string
{
    if ($id < 1) {
        return rtrim(BASE_URL, '/') . '/admin/kullanici/yeni';
    }
    return rtrim(BASE_URL, '/') . '/admin/kullanici/' . $id;
}

function admin_user_roles(): array
{
    return [
        'ogrenci' => 'Öğrenci',
        'ogretmen' => 'Hoca',
        'musteri' => 'Mağaza müşterisi',
        'admin' => 'Yönetici',
    ];
}

function admin_active_admin_count(int $exceptId = 0): int
{
    $sql = "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status IN ('aktif','bekliyor')";
    $args = [];
    if ($exceptId > 0) {
        $sql .= ' AND id <> ?';
        $args[] = $exceptId;
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    return (int) $st->fetchColumn();
}

function admin_delete_user(int $id, int $actorId, bool $allowSelf = false): string
{
    if ($id < 1) {
        throw new RuntimeException('Kullanıcı bulunamadı.');
    }
    if ($id === $actorId && !$allowSelf) {
        throw new RuntimeException('Kendi hesabınızı silemezsiniz.');
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $person = $st->fetch();
    if (!$person) {
        throw new RuntimeException('Kullanıcı bulunamadı.');
    }
    $isAdmin = ($person['role'] ?? '') === 'admin' && in_array((string) ($person['status'] ?? ''), ['aktif', 'bekliyor'], true);
    if ($isAdmin && admin_active_admin_count($id) < 1) {
        throw new RuntimeException('Son yönetici hesabı silinemez.');
    }
    $linked = 0;
    $groups = db()->prepare('SELECT COUNT(*) FROM class_groups WHERE teacher_id = ?');
    $groups->execute([$id]);
    $linked += (int) $groups->fetchColumn();
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM class_group_teachers WHERE teacher_id = ?');
        $st->execute([$id]);
        $linked += (int) $st->fetchColumn();
    } catch (Throwable) {
    }
    if ($linked > 0) {
        throw new RuntimeException('Bu hoca gruplara bağlı. Önce grupların hocasını değiştirin veya grupları silin.');
    }

    $orders = 0;
    $pays = 0;
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
        $st->execute([$id]);
        $orders = (int) $st->fetchColumn();
    } catch (Throwable) {
    }
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM payments WHERE user_id = ?');
        $st->execute([$id]);
        $pays = (int) $st->fetchColumn();
    } catch (Throwable) {
    }
    if ($orders > 0 || $pays > 0) {
        db()->prepare("UPDATE users SET status = 'pasif' WHERE id = ?")->execute([$id]);
        return 'Sipariş veya ödeme kaydı bağlı. Silinmedi, hesap pasife alındı.';
    }

    db_try_exec('DELETE FROM enrollments WHERE student_id = ?', [$id]);
    db_try_exec('DELETE FROM notifications WHERE user_id = ?', [$id]);
    db_try_exec('DELETE FROM messages WHERE thread_user_id = ? OR from_user_id = ?', [$id, $id]);
    db_try_exec('DELETE FROM student_questions WHERE student_id = ? OR teacher_id = ?', [$id, $id]);
    db_try_exec('DELETE FROM homework_subs WHERE student_id = ?', [$id]);
    db_try_exec('DELETE FROM attendance WHERE student_id = ?', [$id]);
    db_try_exec('DELETE FROM test_attempts WHERE student_id = ?', [$id]);
    db_try_exec('DELETE FROM shop_books WHERE user_id = ?', [$id]);
    db_try_exec('DELETE FROM certificates WHERE student_id = ?', [$id]);
    db_try_exec('UPDATE live_chat SET user_id = NULL WHERE user_id = ?', [$id]);
    db_try_exec('DELETE FROM tests WHERE teacher_id = ?', [$id]);
    db_try_exec('DELETE FROM lesson_notes WHERE teacher_id = ?', [$id]);
    db_try_exec('DELETE FROM recordings WHERE teacher_id = ?', [$id]);
    db_try_exec('DELETE FROM live_schedule WHERE teacher_id = ?', [$id]);
    db_try_exec('DELETE FROM live_rooms WHERE teacher_id = ?', [$id]);
    db_try_exec('DELETE FROM class_group_teachers WHERE teacher_id = ?', [$id]);
    db_try_exec('DELETE FROM addresses WHERE user_id = ?', [$id]);

    $avatarRel = trim((string) ($person['avatar'] ?? ''));
    try {
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    } catch (Throwable) {
        db()->prepare("UPDATE users SET status = 'pasif' WHERE id = ?")->execute([$id]);
        return 'Bağlı kayıtlar var. Silinmedi, hesap pasife alındı.';
    }
    if ($avatarRel !== '') {
        user_delete_avatar_file($avatarRel);
    }
    return $allowSelf ? 'Hesabınız silindi.' : 'Kullanıcı silindi.';
}

function admin_save_user(int $id, array $in, int $actorId): int
{
    $name = trim((string) ($in['name'] ?? ''));
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $phone = trim((string) ($in['phone'] ?? ''));
    $city = trim((string) ($in['city'] ?? ''));
    $bio = trim((string) ($in['bio'] ?? ''));
    $role = (string) ($in['role'] ?? 'ogrenci');
    $status = (string) ($in['status'] ?? 'aktif');
    $password = (string) ($in['password'] ?? '');
    $roles = admin_user_roles();
    if ($name === '' || mb_strlen($name) > 120) {
        throw new RuntimeException('Ad soyad girin.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Geçerli e-posta girin.');
    }
    if (!isset($roles[$role])) {
        throw new RuntimeException('Geçersiz rol.');
    }
    if (!in_array($status, ['aktif', 'pasif', 'bekliyor'], true)) {
        throw new RuntimeException('Geçersiz hesap durumu.');
    }
    $isNew = $id < 1;
    if ($isNew && strlen($password) < 8) {
        throw new RuntimeException('Şifre en az 8 karakter olmalı.');
    }
    if (!$isNew && $password !== '' && strlen($password) < 8) {
        throw new RuntimeException('Yeni şifre en az 8 karakter olmalı.');
    }

    $dup = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $dup->execute([$email, max(0, $id)]);
    if ($dup->fetch()) {
        throw new RuntimeException('Bu e-posta başka bir hesapta kayıtlı.');
    }

    if (!$isNew) {
        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        $prev = $st->fetch();
        if (!$prev) {
            throw new RuntimeException('Kullanıcı bulunamadı.');
        }
        $wasAdmin = ($prev['role'] ?? '') === 'admin' && in_array((string) ($prev['status'] ?? ''), ['aktif', 'bekliyor'], true);
        $willAdmin = $role === 'admin' && in_array($status, ['aktif', 'bekliyor'], true);
        if ($wasAdmin && !$willAdmin && admin_active_admin_count($id) < 1) {
            throw new RuntimeException('Son yönetici hesabı düşürülemez veya pasife alınamaz.');
        }
        if ($id === $actorId && $role !== 'admin') {
            throw new RuntimeException('Kendi rolünüzü yöneticiden çıkaramazsınız.');
        }
        if ($id === $actorId && $status === 'pasif') {
            throw new RuntimeException('Kendi hesabınızı pasife alamazsınız.');
        }
    }

    $cols = users_columns();
    $slug = null;
    if (isset($cols['slug'])) {
        if ($role === 'ogretmen' && function_exists('academy_unique_slug')) {
            $keep = !$isNew ? trim((string) ($prev['slug'] ?? '')) : '';
            $slug = $keep !== '' ? $keep : academy_unique_slug('users', $name, $isNew ? 0 : $id);
        } elseif (!$isNew) {
            $slug = $prev['slug'] ?? null;
        }
    }

    if ($isNew) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $fields = ['name', 'email', 'password', 'role', 'phone', 'status'];
        $vals = [$name, $email, $hash, $role, $phone !== '' ? $phone : null, $status];
        if (isset($cols['city'])) {
            $fields[] = 'city';
            $vals[] = $city !== '' ? $city : null;
        }
        if (isset($cols['bio'])) {
            $fields[] = 'bio';
            $vals[] = $bio !== '' ? $bio : null;
        }
        if (isset($cols['slug'])) {
            $fields[] = 'slug';
            $vals[] = $slug;
        }
        $ph = implode(',', array_fill(0, count($fields), '?'));
        db()->prepare('INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . $ph . ')')->execute($vals);
        $id = (int) db()->lastInsertId();
        if ($id < 1) {
            throw new RuntimeException('Kullanıcı oluşturulamadı.');
        }
    } else {
        $sets = ['name = ?', 'email = ?', 'role = ?', 'phone = ?', 'status = ?'];
        $vals = [$name, $email, $role, $phone !== '' ? $phone : null, $status];
        if (isset($cols['city'])) {
            $sets[] = 'city = ?';
            $vals[] = $city !== '' ? $city : null;
        }
        if (isset($cols['bio'])) {
            $sets[] = 'bio = ?';
            $vals[] = $bio !== '' ? $bio : null;
        }
        if (isset($cols['slug'])) {
            $sets[] = 'slug = ?';
            $vals[] = $slug;
        }
        if ($password !== '') {
            $sets[] = 'password = ?';
            $vals[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $vals[] = $id;
        db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    if (isset($cols['avatar'])) {
        $prevAvatar = !$isNew ? (string) ($prev['avatar'] ?? '') : '';
        user_store_avatar_upload($id, $prevAvatar);
    }

    return $id;
}

function user_enrollments(int $userId): array
{
    $st = db()->prepare(
        'SELECT e.*, g.name AS group_name, pr.title AS program_title, pk.name AS package_name
         FROM enrollments e
         JOIN class_groups g ON g.id = e.group_id
         LEFT JOIN programs pr ON pr.id = g.program_id
         LEFT JOIN packages pk ON pk.id = e.package_id
         WHERE e.student_id = ?
         ORDER BY e.id DESC'
    );
    $st->execute([$userId]);
    $rows = $st->fetchAll();
    return $rows;
}

function user_paid_payments(int $userId): array
{
    $st = db()->prepare(
        "SELECT p.*, pk.name AS package_name, pr.title AS program_title, g.name AS group_name
         FROM payments p
         LEFT JOIN packages pk ON pk.id = p.package_id
         LEFT JOIN programs pr ON pr.id = p.program_id
         LEFT JOIN class_groups g ON g.id = p.group_id
         WHERE p.user_id = ? AND p.status = 'odendi'
         ORDER BY p.id DESC"
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function user_all_payments(int $userId): array
{
    $st = db()->prepare(
        "SELECT p.*, pk.name AS package_name, pr.title AS program_title, g.name AS group_name
         FROM payments p
         LEFT JOIN packages pk ON pk.id = p.package_id
         LEFT JOIN programs pr ON pr.id = p.program_id
         LEFT JOIN class_groups g ON g.id = p.group_id
         WHERE p.user_id = ?
         ORDER BY p.id DESC"
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function user_book_orders(int $userId): array
{
    $st = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
    $st->execute([$userId]);
    $orders = $st->fetchAll();
    if (!$orders) {
        return [];
    }
    $ids = array_map(static fn(array $o): int => (int) $o['id'], $orders);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $it = db()->prepare(
        "SELECT i.order_id, i.qty, i.price, b.title
         FROM order_items i JOIN books b ON b.id = i.book_id
         WHERE i.order_id IN ($in)"
    );
    $it->execute($ids);
    $by = [];
    foreach ($it as $row) {
        $by[(int) $row['order_id']][] = $row;
    }
    foreach ($orders as &$o) {
        $o['items'] = $by[(int) $o['id']] ?? [];
        $titles = array_map(static fn(array $i): string => (string) $i['title'], $o['items']);
        $o['title'] = $titles ? implode(', ', $titles) : ('Sipariş #' . $o['id']);
    }
    unset($o);
    return $orders;
}

function user_shop_books(int $userId): array
{
    $st = db()->prepare(
        'SELECT sb.*, b.title, b.slug, b.is_digital
         FROM student_books sb JOIN books b ON b.id = sb.book_id
         WHERE sb.user_id = ?
         ORDER BY sb.id DESC'
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function payment_title(array $p): string
{
    if (!empty($p['package_name'])) {
        return (string) $p['package_name'];
    }
    if (!empty($p['program_title'])) {
        return (string) $p['program_title'];
    }
    $basket = json_decode((string) ($p['basket_json'] ?? '[]'), true);
    if (is_array($basket) && !empty($basket[0]['name'])) {
        return (string) $basket[0]['name'];
    }
    return function_exists('payment_kind_label')
        ? payment_kind_label((string) ($p['kind'] ?? ''))
        : (string) ($p['kind'] ?? 'Ödeme');
}

function pay_status_word(string $status): string
{
    return match ($status) {
        'odendi' => 'ödendi',
        'bekliyor' => 'bekliyor',
        'basarisiz' => 'başarısız',
        default => $status,
    };
}

function user_purchased_packages(int $userId): array
{
    $st = db()->prepare(
        "SELECT p.id, p.kind, p.total, p.paid_at, p.created_at, p.status,
                pk.name AS package_name, pk.duration_days, g.name AS group_name, pr.title AS program_title
         FROM payments p
         LEFT JOIN packages pk ON pk.id = p.package_id
         LEFT JOIN class_groups g ON g.id = p.group_id
         LEFT JOIN programs pr ON pr.id = p.program_id
         WHERE p.user_id = ? AND p.status = 'odendi' AND p.kind IN ('uyelik_ders','uyelik_magaza','program')
         ORDER BY p.id DESC"
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function update_user_contact(int $userId, string $phone, string $city, ?string $name = null): void
{
    $phone = mb_substr(trim($phone), 0, 30);
    $city = mb_substr(trim($city), 0, 80);
    if ($name !== null) {
        $name = mb_substr(trim($name), 0, 120);
        db()->prepare('UPDATE users SET phone = ?, city = ?, name = ? WHERE id = ?')
            ->execute([$phone, $city, $name, $userId]);
        return;
    }
    db()->prepare('UPDATE users SET phone = ?, city = ? WHERE id = ?')
        ->execute([$phone, $city, $userId]);
}

function change_own_password(array $user, string $current, string $next, string $confirm): void
{
    $min = 8;
    if ($next !== $confirm) {
        throw new RuntimeException('Yeni şifreler eşleşmiyor.');
    }
    if (strlen($next) < $min) {
        throw new RuntimeException('Yeni şifre en az ' . $min . ' karakter olmalı.');
    }
    $hash = (string) ($user['password'] ?? '');
    $google = !empty($user['google_id']);
    if ($current === '') {
        if (!$google) {
            throw new RuntimeException('Mevcut şifrenizi girin.');
        }
    } elseif ($hash === '' || !password_verify($current, $hash)) {
        throw new RuntimeException('Mevcut şifre hatalı.');
    }
    if ($hash !== '' && password_verify($next, $hash)) {
        throw new RuntimeException('Yeni şifre eskisiyle aynı olamaz.');
    }
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([password_hash($next, PASSWORD_DEFAULT), (int) $user['id']]);
}

function handle_own_password_post(array $user, string &$ok, string &$err): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || post('act') !== 'password') {
        return false;
    }
    try {
        change_own_password($user, post('current_password'), post('new_password'), post('new_password2'));
        $ok = 'Şifreniz güncellendi.';
        $err = '';
    } catch (Throwable $e) {
        $ok = '';
        $err = $e->getMessage();
    }
    return true;
}

function delete_own_account(array $user, string $password): string
{
    $id = (int) ($user['id'] ?? 0);
    if ($id < 1) {
        throw new RuntimeException('Hesap bulunamadı.');
    }
    $hash = (string) ($user['password'] ?? '');
    $google = !empty($user['google_id']);
    if ($password === '') {
        if (!$google || $hash !== '') {
            throw new RuntimeException('Hesabı silmek için şifrenizi girin.');
        }
    } elseif ($hash === '' || !password_verify($password, $hash)) {
        throw new RuntimeException('Şifre hatalı.');
    }
    return admin_delete_user($id, $id, true);
}

function handle_own_delete_post(array $user, string &$ok, string &$err): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || post('act') !== 'delete_account') {
        return false;
    }
    try {
        $msg = delete_own_account($user, post('current_password'));
        $role = (string) ($user['role'] ?? '');
        logout_user();
        flash_ok($msg);
        header('Location: ' . ($role === 'musteri' ? page_url('giris-magaza') : page_url('giris-ders')));
        exit;
    } catch (Throwable $e) {
        $ok = '';
        $err = $e->getMessage();
    }
    return true;
}

function handle_own_account_post(array &$user, string &$ok, string &$err): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    $act = post('act');
    if ($act === 'password') {
        return handle_own_password_post($user, $ok, $err);
    }
    if ($act === 'delete_account') {
        return handle_own_delete_post($user, $ok, $err);
    }
    if ($act !== '' && $act !== 'profile') {
        return false;
    }
    try {
        $name = trim(post('name'));
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('Ad soyad en az 2 karakter olmalı.');
        }
        update_user_contact((int) $user['id'], post('phone'), post('city'), $name);
        $stored = user_store_avatar_upload((int) $user['id'], (string) ($user['avatar'] ?? ''));
        if ($stored === null && post('avatar_remove') === '1') {
            user_clear_avatar((int) $user['id'], (string) ($user['avatar'] ?? ''));
        }
        $fresh = refresh_current_user((int) $user['id']);
        if ($fresh) {
            $user = $fresh;
        }
        $ok = 'Bilgileriniz kaydedildi.';
        $err = '';
    } catch (Throwable $e) {
        $ok = '';
        $err = $e->getMessage();
    }
    return true;
}

function profile_contact_form(array $user, string $intro = ''): void
{
    $hasPhoto = trim((string) ($user['avatar'] ?? '')) !== '';
    if ($intro === '') {
        $intro = 'Ad, telefon, şehir ve profil fotoğrafını güncelleyebilirsiniz. E-posta giriş anahtarıdır, değiştirilemez.';
    }
    ?>
<form method="post" enctype="multipart/form-data" class="card mt-6 grid max-w-xl gap-4 p-6">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="profile">
  <p class="font-extrabold">Profil</p>
  <p class="text-sm text-muted"><?= e($intro) ?></p>
  <label class="text-sm font-bold">Ad soyad
    <input required name="name" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) $user['name']) ?>">
  </label>
  <label class="text-sm font-bold">Telefon
    <input name="phone" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($user['phone'] ?? '')) ?>">
  </label>
  <label class="text-sm font-bold">Şehir
    <input name="city" class="mt-1 w-full rounded-xl border px-3 py-2" value="<?= e((string) ($user['city'] ?? '')) ?>">
  </label>
  <label class="text-sm font-bold">E-posta
    <input readonly class="mt-1 w-full rounded-xl border bg-soft px-3 py-2 text-muted" value="<?= e((string) $user['email']) ?>">
  </label>
  <label class="text-sm font-bold">Profil fotoğrafı
    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-xl border px-3 py-2 font-normal">
  </label>
  <p class="text-xs text-muted">JPG, PNG veya WEBP, en fazla 4 MB. Bu fotoğrafı yalnızca yöneticiler görür; sitede ve hocalarda görünmez.</p>
  <?php if ($hasPhoto): ?>
  <label class="flex items-center gap-2 text-sm font-bold">
    <input type="checkbox" name="avatar_remove" value="1"> Fotoğrafı kaldır
  </label>
  <?php endif; ?>
  <button class="btn-primary" type="submit">Kaydet</button>
</form>
    <?php
}

function profile_delete_form(array $user): void
{
    $google = !empty($user['google_id']);
    $needPass = !$google || (string) ($user['password'] ?? '') !== '';
    ?>
<section class="card mt-8 max-w-xl border-red-200 p-6">
  <p class="font-extrabold text-accent">Hesabımı sil</p>
  <p class="mt-2 text-sm text-muted">Hesabınız ve panel kayıtlarınız silinir. Sipariş veya ödeme varsa hesap silinmez, pasife alınır. Bu işlem geri alınamaz.</p>
  <form method="post" class="mt-4 grid gap-3" onsubmit="return confirm('Hesabınız kalıcı olarak silinsin mi? Bu işlem geri alınamaz.');">
    <?= csrf_field() ?>
    <input type="hidden" name="act" value="delete_account">
    <label class="text-sm font-bold"><?= $needPass ? 'Onay için şifreniz' : 'Onay (Google hesabı, şifre yoksa boş bırakın)' ?>
      <input type="password" name="current_password" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="current-password" <?= $needPass ? 'required' : '' ?>>
    </label>
    <button type="submit" class="btn-outline text-accent">Hesabımı sil</button>
  </form>
</section>
    <?php
}

function profile_password_form(array $user): void
{
    $google = !empty($user['google_id']);
    $min = 8;
    ?>
<form method="post" class="card mt-6 grid max-w-xl gap-4 p-6" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="password">
  <p class="font-extrabold">Şifre değiştir</p>
  <?php if ($google): ?>
  <p class="text-sm text-muted">Google ile giriş yaptıysanız mevcut şifreyi boş bırakıp yeni bir şifre belirleyebilirsiniz. Sonra e-posta ve şifre ile de girebilirsiniz.</p>
  <?php else: ?>
  <p class="text-sm text-muted">Güvenlik için önce mevcut şifrenizi girin. Yeni şifre en az <?= (int) $min ?> karakter olmalı.</p>
  <?php endif; ?>
  <label class="text-sm font-bold"><?= $google ? 'Mevcut şifre (varsa)' : 'Mevcut şifre' ?>
    <input type="password" name="current_password" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="current-password" <?= $google ? '' : 'required' ?>>
  </label>
  <label class="text-sm font-bold">Yeni şifre
    <input type="password" name="new_password" required minlength="<?= (int) $min ?>" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="new-password">
  </label>
  <label class="text-sm font-bold">Yeni şifre (tekrar)
    <input type="password" name="new_password2" required minlength="<?= (int) $min ?>" class="mt-1 w-full rounded-xl border px-3 py-2" autocomplete="new-password">
  </label>
  <button class="btn-primary" type="submit">Şifreyi güncelle</button>
</form>
    <?php
}

function refresh_current_user(int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

function users_admin_rows(): array
{
    $sql = 'SELECT u.id, u.name, u.email, u.role, u.phone, u.city, u.status, u.avatar,
                   u.membership_expires_at, u.created_at,
                   (SELECT MAX(e.expires_at) FROM enrollments e WHERE e.student_id = u.id) AS enroll_expires,
                   (SELECT SUM(e.expires_at IS NULL) FROM enrollments e WHERE e.student_id = u.id) AS enroll_open
            FROM users u
            ORDER BY ' . catalog_order_sql('u', 'users');
    return db()->query($sql)->fetchAll();
}

function membership_from_admin_row(array $row): array
{
    if (in_array((string) ($row['role'] ?? ''), ['admin', 'ogretmen'], true)) {
        return live_membership_state($row, []);
    }
    if (($row['role'] ?? '') === 'ogrenci') {
        $enrolls = [];
        if ((int) ($row['enroll_open'] ?? 0) > 0) {
            $enrolls[] = ['expires_at' => null];
        } elseif (!empty($row['enroll_expires'])) {
            $enrolls[] = ['expires_at' => $row['enroll_expires']];
        }
        return live_membership_state($row, $enrolls);
    }
    return live_membership_state($row, []);
}
