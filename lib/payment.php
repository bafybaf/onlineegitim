<?php
function payment_new_oid(int $id): string
{
    return 'OI' . $id . strtoupper(bin2hex(random_bytes(4)));
}

function payment_create(int $userId, string $kind, int $total, array $basket, array $extra = []): array
{
    $pdo = db();
    $programId = (int) ($extra['program_id'] ?? 0);
    $groupId = (int) ($extra['group_id'] ?? 0);
    $packageId = (int) ($extra['package_id'] ?? 0);
    $addrId = (int) ($extra['address_id'] ?? 0);
    $addrJson = isset($extra['address']) && is_array($extra['address'])
        ? json_encode($extra['address'], JSON_UNESCAPED_UNICODE)
        : null;
    $pdo->prepare('INSERT INTO payments (merchant_oid, user_id, kind, total, status, program_id, group_id, package_id, ship_mode, coupon, basket_json, address_id, address_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            'TMP' . bin2hex(random_bytes(8)),
            $userId,
            $kind,
            $total,
            'bekliyor',
            $programId > 0 ? $programId : null,
            $groupId > 0 ? $groupId : null,
            $packageId > 0 ? $packageId : null,
            $extra['ship_mode'] ?? null,
            $extra['coupon'] ?? null,
            json_encode($basket, JSON_UNESCAPED_UNICODE),
            $addrId > 0 ? $addrId : null,
            $addrJson,
        ]);
    $id = (int) $pdo->lastInsertId();
    $oid = payment_new_oid($id);
    $pdo->prepare('UPDATE payments SET merchant_oid = ? WHERE id = ?')->execute([$oid, $id]);
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch();
}

function payment_settle_now(array $payment): array
{
    if (($payment['status'] ?? '') !== 'odendi') {
        payment_fulfill($payment);
    }
    $fresh = payment_by_oid((string) ($payment['merchant_oid'] ?? ''));
    return $fresh ?: $payment;
}

function payment_by_oid(string $oid): ?array
{
    $st = db()->prepare('SELECT * FROM payments WHERE merchant_oid = ?');
    $st->execute([$oid]);
    $row = $st->fetch();
    return $row ?: null;
}

function payment_basket(array $payment): array
{
    $rows = json_decode((string) $payment['basket_json'], true);
    return is_array($rows) ? $rows : [];
}

function payment_fulfill(array $payment): void
{
    $kind = (string) ($payment['kind'] ?? '');
    if (($payment['status'] ?? '') === 'odendi') {
        if ($kind === 'kitap' && !empty($payment['order_id'])) {
            return;
        }
        if (in_array($kind, ['program', 'uyelik_magaza', 'uyelik_ders'], true)) {
            return;
        }
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
        $st->execute([(int) $payment['id']]);
        $row = $st->fetch();
        if (!$row || $row['status'] === 'odendi') {
            $pdo->commit();
            return;
        }
        if ($row['kind'] === 'kitap') {
            fulfill_book_payment($pdo, $row);
        } elseif ($row['kind'] === 'uyelik_magaza') {
            fulfill_shop_membership($pdo, $row);
        } elseif ($row['kind'] === 'uyelik_ders') {
            fulfill_class_membership($pdo, $row);
        } else {
            fulfill_program_payment($pdo, $row);
        }
        $pdo->prepare('UPDATE payments SET status = ?, paid_at = NOW(), fail_reason = NULL WHERE id = ?')
            ->execute(['odendi', $row['id']]);
        $pdo->commit();
        $paidRow = $row;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    if (isset($paidRow) && function_exists('notify_payment_paid')) {
        notify_payment_paid($paidRow);
    }
}

function fulfill_book_payment(PDO $pdo, array $payment): void
{
    $basket = payment_basket($payment);
    $mode = (string) ($payment['ship_mode'] ?: 'kargo');
    $status = $mode === 'dijital' ? 'Dijital teslim' : 'Hazırlanıyor';
    $snap = function_exists('payment_address_snapshot') ? payment_address_snapshot($payment) : [];
    $addrId = (int) ($payment['address_id'] ?? ($snap['id'] ?? 0));
    $pdo->prepare('INSERT INTO orders (user_id, total, status, ship_mode, coupon, merchant_oid, pay_status, address_id, ship_name, ship_phone, ship_city, ship_district, ship_line) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $payment['user_id'],
            $payment['total'],
            $status,
            $mode,
            $payment['coupon'] ?: null,
            $payment['merchant_oid'],
            'odendi',
            $addrId > 0 ? $addrId : null,
            $snap['name'] ?? null,
            $snap['phone'] ?? null,
            $snap['city'] ?? null,
            $snap['district'] ?? null,
            $snap['line'] ?? null,
        ]);
    $oid = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO order_items (order_id, book_id, qty, price) VALUES (?,?,?,?)');
    $sb = $pdo->prepare('INSERT INTO student_books (user_id, book_id, status, kind) VALUES (?,?,?,?)');
    $stk = $pdo->prepare('UPDATE books SET stock = stock - ? WHERE id = ? AND is_digital = 0 AND stock >= ?');
    foreach ($basket as $line) {
        $bookId = (int) ($line['book_id'] ?? 0);
        $qty = max(1, (int) ($line['qty'] ?? 1));
        $price = (int) ($line['price'] ?? 0);
        if ($bookId < 1) {
            continue;
        }
        $ins->execute([$oid, $bookId, $qty, $price]);
        $isDigital = !empty($line['is_digital']);
        $kind = $isDigital || $mode === 'dijital' ? 'Dijital PDF' : 'Basılı';
        $bookStatus = $kind === 'Dijital PDF' ? 'İndirilebilir' : 'Hazırlanıyor';
        $sb->execute([$payment['user_id'], $bookId, $bookStatus, $kind]);
        if (!$isDigital && $mode !== 'dijital') {
            $stk->execute([$qty, $bookId, $qty]);
        }
    }
    $pdo->prepare('UPDATE payments SET order_id = ? WHERE id = ?')->execute([$oid, $payment['id']]);
}

function fulfill_program_payment(PDO $pdo, array $payment): void
{
    $groupId = (int) ($payment['group_id'] ?? 0);
    $studentId = (int) $payment['user_id'];
    if ($groupId < 1) {
        return;
    }
    $chk = $pdo->prepare('SELECT id FROM enrollments WHERE student_id = ? AND group_id = ?');
    $chk->execute([$studentId, $groupId]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO enrollments (student_id, group_id, progress) VALUES (?,?,0)')
            ->execute([$studentId, $groupId]);
    }
}

function membership_duration_from_payment(PDO $pdo, array $payment): int
{
    $basket = payment_basket($payment);
    $days = (int) ($basket[0]['duration_days'] ?? 0);
    if ($days > 0) {
        return $days;
    }
    $pkgId = (int) ($payment['package_id'] ?? ($basket[0]['package_id'] ?? 0));
    if ($pkgId > 0) {
        $st = $pdo->prepare('SELECT duration_days FROM packages WHERE id = ?');
        $st->execute([$pkgId]);
        $days = (int) $st->fetchColumn();
    }
    return $days > 0 ? $days : 365;
}

function fulfill_shop_membership(PDO $pdo, array $payment): void
{
    $days = membership_duration_from_payment($pdo, $payment);
    $userId = (int) $payment['user_id'];
    $st = $pdo->prepare('SELECT membership_expires_at FROM users WHERE id = ? FOR UPDATE');
    $st->execute([$userId]);
    $cur = $st->fetchColumn();
    $base = time();
    if (is_string($cur) && $cur !== '' && strtotime($cur) > $base) {
        $base = (int) strtotime($cur);
    }
    $expires = date('Y-m-d H:i:s', $base + ($days * 86400));
    $pdo->prepare('UPDATE users SET status = ?, membership_expires_at = ? WHERE id = ?')
        ->execute(['aktif', $expires, $userId]);
}

function fulfill_class_membership(PDO $pdo, array $payment): void
{
    $userId = (int) $payment['user_id'];
    $groupId = (int) ($payment['group_id'] ?? 0);
    $packageId = (int) ($payment['package_id'] ?? 0);
    if ($packageId < 1) {
        $basket = payment_basket($payment);
        $packageId = (int) ($basket[0]['package_id'] ?? 0);
    }
    $days = membership_duration_from_payment($pdo, $payment);
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['aktif', $userId]);
    if ($groupId < 1) {
        return;
    }
    $chk = $pdo->prepare('SELECT id, expires_at FROM enrollments WHERE student_id = ? AND group_id = ? FOR UPDATE');
    $chk->execute([$userId, $groupId]);
    $en = $chk->fetch();
    $base = time();
    if ($en && !empty($en['expires_at']) && strtotime((string) $en['expires_at']) > $base) {
        $base = (int) strtotime((string) $en['expires_at']);
    }
    $expires = date('Y-m-d H:i:s', $base + ($days * 86400));
    $pkg = $packageId > 0 ? $packageId : null;
    if ($en) {
        $pdo->prepare('UPDATE enrollments SET package_id = ?, expires_at = ?, status = ? WHERE id = ?')
            ->execute([$pkg, $expires, 'aktif', $en['id']]);
    } else {
        $pdo->prepare('INSERT INTO enrollments (student_id, group_id, progress, package_id, started_at, expires_at, status) VALUES (?,?,0,?,NOW(),?,?)')
            ->execute([$userId, $groupId, $pkg, $expires, 'aktif']);
    }
    if ($packageId > 0) {
        $pst = $pdo->prepare('SELECT gift_book_id FROM packages WHERE id = ?');
        $pst->execute([$packageId]);
        $gift = (int) ($pst->fetchColumn() ?: 0);
        if ($gift > 0 && function_exists('academy_gift_book')) {
            academy_gift_book($pdo, $userId, $gift);
        }
    }
}

function payment_fail(array $payment, string $reason): void
{
    if (($payment['status'] ?? '') === 'odendi') {
        return;
    }
    db()->prepare('UPDATE payments SET status = ?, fail_reason = ? WHERE id = ? AND status = ?')
        ->execute(['basarisiz', mb_substr($reason, 0, 255), $payment['id'], 'bekliyor']);
}
