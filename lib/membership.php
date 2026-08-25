<?php

function paket_admin_url(int $id): string
{
    if ($id < 1) {
        return rtrim(BASE_URL, '/') . '/admin/paket/yeni';
    }
    return rtrim(BASE_URL, '/') . '/admin/paket/' . $id;
}

function admin_save_package(int $id, array $in): int
{
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        throw new RuntimeException('Paket adı girin.');
    }
    $price = max(1, (int) ($in['price'] ?? 0));
    $days = max(1, (int) ($in['duration_days'] ?? 0));
    $active = !empty($in['active']) ? 1 : 0;
    $access = (($in['access_type'] ?? '') === 'sadece_video') ? 'sadece_video' : 'canli_video';
    $gift = max(0, (int) ($in['gift_book_id'] ?? 0));
    $groupId = max(0, (int) ($in['default_group_id'] ?? 0));
    $programId = max(0, (int) ($in['program_id'] ?? 0));

    if ($groupId > 0) {
        $st = db()->prepare('SELECT id, program_id FROM class_groups WHERE id = ?');
        $st->execute([$groupId]);
        $g = $st->fetch();
        if (!$g) {
            throw new RuntimeException('Grup bulunamadı.');
        }
        $programId = (int) $g['program_id'];
    } elseif ($programId > 0) {
        $st = db()->prepare('SELECT id FROM programs WHERE id = ?');
        $st->execute([$programId]);
        if (!$st->fetch()) {
            throw new RuntimeException('Program bulunamadı.');
        }
    }

    if ($gift > 0) {
        $st = db()->prepare('SELECT id FROM books WHERE id = ?');
        $st->execute([$gift]);
        if (!$st->fetch()) {
            throw new RuntimeException('Hediye kitap bulunamadı.');
        }
    }

    $progVal = $programId > 0 ? $programId : null;
    $grpVal = $groupId > 0 ? $groupId : null;
    $giftVal = $gift > 0 ? $gift : null;

    if ($id < 1) {
        db()->prepare(
            "INSERT INTO packages (kind, program_id, default_group_id, name, duration_days, price, auto_delete, active, access_type, gift_book_id)
             VALUES ('ders', ?, ?, ?, ?, ?, 0, ?, ?, ?)"
        )->execute([$progVal, $grpVal, $name, $days, $price, $active, $access, $giftVal]);
        $id = (int) db()->lastInsertId();
        if ($id < 1) {
            throw new RuntimeException('Paket oluşturulamadı.');
        }
        return $id;
    }

    $st = db()->prepare("SELECT id FROM packages WHERE id = ? AND kind = 'ders'");
    $st->execute([$id]);
    if (!$st->fetch()) {
        throw new RuntimeException('Paket bulunamadı.');
    }
    db()->prepare(
        "UPDATE packages SET program_id = ?, default_group_id = ?, name = ?, duration_days = ?, price = ?, active = ?, access_type = ?, gift_book_id = ?
         WHERE id = ? AND kind = 'ders'"
    )->execute([$progVal, $grpVal, $name, $days, $price, $active, $access, $giftVal, $id]);
    return $id;
}

function package_by_id(int $id): ?array
{
    $st = db()->prepare(
        'SELECT p.*, g.name AS group_name
         FROM packages p
         LEFT JOIN class_groups g ON g.id = p.default_group_id
         WHERE p.id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    if (function_exists('package_canonical_name')) {
        $row['name'] = package_canonical_name($row);
    }
    return $row;
}

function packages_active(string $kind): array
{
    $st = db()->prepare(
        'SELECT p.*, g.name AS group_name, pr.title AS program_title, pr.slug AS program_slug
         FROM packages p
         LEFT JOIN class_groups g ON g.id = p.default_group_id
         LEFT JOIN programs pr ON pr.id = p.program_id
         WHERE p.kind = ? AND p.active = 1
         ORDER BY ' . catalog_order_sql('p', 'packages')
    );
    $st->execute([$kind]);
    $rows = $st->fetchAll();
    foreach ($rows as &$row) {
        if (function_exists('package_canonical_name')) {
            $row['name'] = package_canonical_name($row);
        }
    }
    unset($row);
    return $rows;
}

function packages_all(): array
{
    $rows = db()->query(
        'SELECT p.*, g.name AS group_name, pr.title AS program_title
         FROM packages p
         LEFT JOIN class_groups g ON g.id = p.default_group_id
         LEFT JOIN programs pr ON pr.id = p.program_id
         WHERE p.kind = \'ders\'
         ORDER BY ' . catalog_order_sql('p', 'packages')
    )->fetchAll();
    foreach ($rows as &$row) {
        if (function_exists('package_canonical_name')) {
            $row['name'] = package_canonical_name($row);
        }
    }
    unset($row);
    return $rows;
}

function membership_is_staff(array $u): bool
{
    return is_admin_role($u['role']) || $u['role'] === 'ogretmen';
}

function shop_membership_valid(array $u): bool
{
    return is_shop_role($u['role']) && ($u['status'] ?? '') === 'aktif';
}

function student_has_active_enrollment(int $studentId): bool
{
    $st = db()->prepare(
        "SELECT 1 FROM enrollments
         WHERE student_id = ?
           AND (status = 'aktif' OR status IS NULL)
           AND (expires_at IS NULL OR expires_at > NOW())
         LIMIT 1"
    );
    $st->execute([$studentId]);
    return (bool) $st->fetch();
}

function membership_paid(array $u): bool
{
    if (membership_is_staff($u)) {
        return true;
    }
    if (($u['status'] ?? '') !== 'aktif') {
        return false;
    }
    if (is_shop_role($u['role'])) {
        return ($u['status'] ?? '') === 'aktif';
    }
    if ($u['role'] === 'ogrenci') {
        return student_has_active_enrollment((int) $u['id']);
    }
    return true;
}

function membership_needs_pay(array $u): bool
{
    if (membership_is_staff($u) || is_shop_role($u['role'])) {
        return false;
    }
    return !membership_paid($u);
}

function membership_pending_payment(int $userId, ?string $kind = null): ?array
{
    if ($kind) {
        $st = db()->prepare('SELECT * FROM payments WHERE user_id = ? AND kind = ? AND status = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$userId, $kind, 'bekliyor']);
    } else {
        $st = db()->prepare("SELECT * FROM payments WHERE user_id = ? AND kind = 'uyelik_ders' AND status = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$userId, 'bekliyor']);
    }
    $row = $st->fetch();
    return $row ?: null;
}

function membership_complete_url(array $u): string
{
    if (is_shop_role($u['role'])) {
        return url('magaza/index.php');
    }
    return page_url('uyelik-ders');
}

function membership_start_checkout(array $user, array $package, ?int $groupId = null): array
{
    if (membership_is_staff($user)) {
        throw new RuntimeException('Yönetici ve öğretmen üyelik ödemez.');
    }
    if (($package['kind'] ?? '') === 'magaza') {
        throw new RuntimeException('Mağaza hesabı ücretsizdir; paket satışı yoktur.');
    }
    $kind = 'uyelik_ders';
    if ($user['role'] !== 'ogrenci') {
        throw new RuntimeException('Ders üyeliği yalnızca öğrenci hesabıyla alınır.');
    }
    $gid = $groupId ?? (int) ($package['default_group_id'] ?? 0);
    $pid = (int) ($package['program_id'] ?? 0);
    if ($kind === 'uyelik_ders' && $gid < 1) {
        throw new RuntimeException('Grup seçin.');
    }
    $pending = membership_pending_payment((int) $user['id'], $kind);
    if (
        $pending
        && (int) ($pending['package_id'] ?? 0) === (int) $package['id']
        && (int) ($pending['group_id'] ?? 0) === $gid
        && (int) $pending['total'] === (int) $package['price']
    ) {
        return payment_settle_now($pending);
    }
    $basket = [[
        'name' => (string) $package['name'],
        'price' => (int) $package['price'],
        'qty' => 1,
        'package_id' => (int) $package['id'],
        'duration_days' => (int) $package['duration_days'],
    ]];
    $payment = payment_create((int) $user['id'], $kind, (int) $package['price'], $basket, [
        'program_id' => $pid,
        'group_id' => $gid,
        'package_id' => (int) $package['id'],
    ]);
    return payment_settle_now($payment);
}

function payment_kind_label(string $kind): string
{
    return match ($kind) {
        'kitap' => 'Kitap',
        'program' => 'Program',
        'uyelik_magaza' => 'Eski mağaza paketi',
        'uyelik_ders' => 'Ders üyeliği',
        default => $kind,
    };
}

function payment_success_href(array $payment): string
{
    return match ($payment['kind'] ?? '') {
        'uyelik_ders', 'program' => url('ogrenci/index.php'),
        'uyelik_magaza' => url('magaza/index.php'),
        default => url('magaza/kitaplarim.php'),
    };
}

function payment_retry_href(?array $payment): string
{
    $kind = $payment['kind'] ?? '';
    if (in_array($kind, ['uyelik_ders', 'program'], true)) {
        $u = current_user();
        return ($u && $u['role'] === 'ogrenci') ? page_url('uyelik-ders') : page_url('kayit-ders');
    }
    if ($kind === 'uyelik_magaza') {
        $u = current_user();
        return ($u && is_shop_role($u['role'])) ? url('magaza/index.php') : page_url('kayit-magaza');
    }
    return page_url('sepet');
}

function payment_login_file(?array $payment): string
{
    return in_array($payment['kind'] ?? '', ['uyelik_ders', 'program'], true)
        ? 'giris-ders.php'
        : 'giris-magaza.php';
}

function can_pay_kind(array $u, string $kind): bool
{
    if (membership_is_staff($u)) {
        return false;
    }
    return match ($kind) {
        'kitap' => is_shop_role($u['role']) && ($u['status'] ?? '') === 'aktif',
        'uyelik_magaza' => false,
        'uyelik_ders', 'program' => $u['role'] === 'ogrenci',
        default => false,
    };
}

function membership_panel_banner(array $u): void
{
    if (!membership_needs_pay($u)) {
        return;
    }
    $href = membership_complete_url($u);
    $msg = ($u['status'] ?? '') === 'bekliyor'
        ? 'Canlı ders üyeliğiniz yok. Bir paket seçin; kayıt hemen hesabınıza düşer.'
        : 'Üyeliğinizin süresi doldu. Yenilemek için paket seçin.';
    echo '<div class="card mb-6 border-navy p-5"><p class="font-extrabold">' . e($msg) . '</p>';
    echo '<a class="btn-primary mt-3 inline-flex" href="' . e($href) . '">Paket seç</a></div>';
}

function register_user_account(string $name, string $email, string $password, string $role, string $phone, string $status = 'bekliyor', ?string $googleId = null): array
{
    if (!in_array($status, ['aktif', 'bekliyor'], true)) {
        $status = 'bekliyor';
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Şifre en az 8 karakter olmalı.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $cols = function_exists('users_columns') ? users_columns() : [];
    if ($googleId && isset($cols['google_id'])) {
        db()->prepare('INSERT INTO users (name, email, password, role, phone, status, google_id) VALUES (?,?,?,?,?,?,?)')
            ->execute([$name, $email, $hash, $role, $phone, $status, $googleId]);
    } else {
        db()->prepare('INSERT INTO users (name, email, password, role, phone, status) VALUES (?,?,?,?,?,?)')
            ->execute([$name, $email, $hash, $role, $phone, $status]);
    }
    $id = (int) db()->lastInsertId();
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $u = $st->fetch();
    if (!$u) {
        throw new RuntimeException('Kullanıcı oluşturulamadı.');
    }
    return $u;
}

function register_membership_user(string $name, string $email, string $password, string $role, string $phone): array
{
    $status = $role === 'musteri' ? 'aktif' : 'bekliyor';
    return register_user_account($name, $email, $password, $role, $phone, $status);
}

function register_shop_user(string $name, string $email, string $password, string $phone): array
{
    return register_user_account($name, $email, $password, 'musteri', $phone, 'aktif');
}

function package_matches_interest(array $pkg, string $interest): bool
{
    if ($interest === '') {
        return false;
    }
    $hay = mb_strtolower(($pkg['name'] ?? '') . ' ' . ($pkg['program_title'] ?? '') . ' ' . ($pkg['group_name'] ?? ''));
    return str_contains($hay, mb_strtolower($interest));
}
