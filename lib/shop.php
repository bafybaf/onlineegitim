<?php

function admin_delete_book(int $id): void
{
    $st = db()->prepare('SELECT id FROM books WHERE id = ?');
    $st->execute([$id]);
    if (!$st->fetch()) {
        throw new RuntimeException('Ürün bulunamadı.');
    }
    $n = db()->prepare('SELECT COUNT(*) FROM order_items WHERE book_id = ?');
    $n->execute([$id]);
    if ((int) $n->fetchColumn() > 0) {
        throw new RuntimeException('Bu ürün siparişte geçiyor. Katalogdan silinemez.');
    }
    db_try_exec('DELETE FROM shop_books WHERE book_id = ?', [$id]);
    db_try_exec('UPDATE packages SET gift_book_id = NULL WHERE gift_book_id = ?', [$id]);
    if (function_exists('media_delete_owner')) {
        media_delete_owner('book', $id);
    }
    db()->prepare('DELETE FROM books WHERE id = ?')->execute([$id]);
}

function shop_date(?string $dt): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    $t = strtotime($dt);
    return $t ? date('d.m.Y H:i', $t) : $dt;
}

function shop_order_status_label(string $status): string
{
    return match ($status) {
        'Hazırlanıyor' => 'Hazırlanıyor',
        'Kargoda' => 'Kargoda',
        'Teslim edildi' => 'Teslim edildi',
        'Dijital teslim' => 'Dijital teslim',
        default => utf8_from_mojibake($status),
    };
}

function shop_pay_status_label(string $status): string
{
    return match ($status) {
        'bekliyor' => 'Ödeme bekleniyor',
        'odendi' => 'Ödendi',
        'basarisiz' => 'Ödeme başarısız',
        default => utf8_from_mojibake($status),
    };
}

function shop_ship_label(string $mode): string
{
    return match ($mode) {
        'kargo' => 'Kargo',
        'dijital' => 'Dijital',
        default => utf8_from_mojibake($mode),
    };
}

function shop_status_class(string $status): string
{
    return match ($status) {
        'Teslim edildi', 'Dijital teslim', 'İndirilebilir', 'odendi' => 'shop-pill shop-pill-ok',
        'Kargoda', 'Hazırlanıyor' => 'shop-pill shop-pill-wait',
        'bekliyor' => 'shop-pill shop-pill-pay',
        'basarisiz' => 'shop-pill shop-pill-bad',
        default => 'shop-pill',
    };
}

function shop_book_downloadable(array $book): bool
{
    $kind = (string) ($book['kind'] ?? '');
    $status = (string) ($book['status'] ?? '');
    $digital = (int) ($book['is_digital'] ?? 0) === 1;
    return $digital
        || str_contains($kind, 'Dijital')
        || $status === 'İndirilebilir'
        || $status === 'Dijital teslim';
}

function shop_membership_state(array $u): array
{
    $expires = $u['membership_expires_at'] ?? null;
    $expires = is_string($expires) && $expires !== '' ? $expires : null;
    $valid = function_exists('shop_membership_valid')
        ? shop_membership_valid($u)
        : (($u['status'] ?? '') === 'aktif');
    $pending = null;
    if (function_exists('membership_pending_payment')) {
        try {
            $pending = membership_pending_payment((int) $u['id'], 'uyelik_magaza')
                ?? membership_pending_payment((int) $u['id']);
        } catch (Throwable) {
            $pending = null;
        }
    }
    $label = 'Aktif';
    $detail = 'Mağaza hesabınız açık. Kitaplar sepetten satın alınca hemen Kitaplarım’a düşer.';
    if (!$valid) {
        $label = ($u['status'] ?? '') === 'bekliyor' ? 'Bekliyor' : 'Kapalı';
        $detail = 'Hesabınız henüz alışverişe açık değil. Destek ile iletişime geçin.';
    }
    return [
        'valid' => $valid,
        'label' => $label,
        'detail' => $detail,
        'expires' => $expires,
        'pending' => $pending,
    ];
}

function shop_pending_payments(int $userId): array
{
    try {
        $st = db()->prepare("SELECT * FROM payments WHERE user_id = ? AND status = 'bekliyor' AND kind <> 'uyelik_magaza' ORDER BY id DESC");
        $st->execute([$userId]);
        return $st->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function shop_empty(string $title, string $text, string $href = '', string $cta = ''): void
{
    echo '<div class="shop-empty">';
    echo '<p class="font-display text-xl">' . e($title) . '</p>';
    echo '<p class="mt-2 text-sm text-muted">' . e($text) . '</p>';
    if ($href !== '' && $cta !== '') {
        echo '<a class="btn-primary mt-4 inline-flex" href="' . e($href) . '">' . e($cta) . '</a>';
    }
    echo '</div>';
}

function siparis_url(int $id): string
{
    return rtrim(BASE_URL, '/') . '/admin/siparis/' . max(0, $id);
}

function siparis_next_status(string $status): ?string
{
    return match ($status) {
        'Hazırlanıyor' => 'Kargoda',
        'Kargoda' => 'Teslim edildi',
        'Dijital teslim' => 'Teslim edildi',
        default => null,
    };
}

function siparis_next_label(string $status): string
{
    return match ($status) {
        'Hazırlanıyor' => 'Kargoya ver',
        'Kargoda' => 'Teslim edildi işaretle',
        'Dijital teslim' => 'Teslim edildi işaretle',
        default => '',
    };
}

function shop_carriers(): array
{
    return [
        'yurtici' => 'Yurtiçi Kargo',
        'aras' => 'Aras Kargo',
        'mng' => 'MNG Kargo',
        'ptt' => 'PTT Kargo',
        'surat' => 'Sürat Kargo',
        'tex' => 'Trendyol Express',
        'hepsijet' => 'HepsiJET',
        'sendeo' => 'Sendeo',
        'dhl' => 'DHL',
        'ups' => 'UPS',
        'diger' => 'Diğer',
    ];
}

function shop_carrier_label(string $code): string
{
    $map = shop_carriers();
    return $map[$code] ?? ($code !== '' ? $code : '');
}

function shop_fulfillment_statuses(string $shipMode): array
{
    if ($shipMode === 'dijital') {
        return ['Dijital teslim', 'Teslim edildi'];
    }
    return ['Hazırlanıyor', 'Kargoda', 'Teslim edildi'];
}

function shop_tracking_url(string $carrier, string $code, string $custom = ''): string
{
    $custom = trim($custom);
    if ($custom !== '' && preg_match('#^https?://#i', $custom)) {
        return $custom;
    }
    $code = trim($code);
    if ($code === '') {
        return '';
    }
    $enc = rawurlencode($code);
    return match ($carrier) {
        'yurtici' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $enc,
        'aras' => 'https://kargotakip.araskargo.com.tr/?code=' . $enc,
        'mng' => 'https://kargotakip.mngkargo.com.tr/?takipNo=' . $enc,
        'ptt' => 'https://gonderitakip.ptt.gov.tr/',
        'surat' => 'https://www.suratkargo.com.tr/KargoTakip/?kargotakipno=' . $enc,
        'tex' => 'https://kargotakip.trendyolexpress.com/?trackingNumber=' . $enc,
        'hepsijet' => 'https://www.hepsijet.com/gonderi-takip/' . $enc,
        'sendeo' => 'https://www.sendeo.com.tr/gonderi-sorgula?code=' . $enc,
        'dhl' => 'https://www.dhl.com/tr-tr/home/bin_walrus/tracking-express.html?submit=1&tracking-id=' . $enc,
        'ups' => 'https://www.ups.com/track?loc=tr_TR&tracknum=' . $enc,
        default => $custom,
    };
}

function shop_customer_orders_url(array $user): string
{
    return (($user['role'] ?? '') === 'ogrenci') ? url('ogrenci/hesap') : url('magaza/siparisler');
}

function shop_ship_events(int $orderId): array
{
    try {
        $st = db()->prepare('SELECT * FROM order_ship_events WHERE order_id = ? ORDER BY id DESC LIMIT 20');
        $st->execute([$orderId]);
        return $st->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function shop_ship_notify_copy(array $order, string $status, string $carrier, string $tracking, string $url, string $extra): array
{
    $oid = (string) ($order['merchant_oid'] ?: '#' . (int) $order['id']);
    $title = match ($status) {
        'Kargoda' => 'Siparişiniz kargoda',
        'Teslim edildi' => 'Siparişiniz teslim edildi',
        'Dijital teslim' => 'Dijital kopyanız hazır',
        default => 'Siparişiniz hazırlanıyor',
    };
    $body = match ($status) {
        'Kargoda' => 'Sipariş ' . $oid . ' kargoya verildi.',
        'Teslim edildi' => 'Sipariş ' . $oid . ' teslim edildi.',
        'Dijital teslim' => 'Sipariş ' . $oid . ' dijital olarak hesabınıza düştü.',
        default => 'Sipariş ' . $oid . ' hazırlanıyor.',
    };
    if ($carrier !== '') {
        $body .= ' Firma: ' . shop_carrier_label($carrier) . '.';
    }
    if ($tracking !== '') {
        $body .= ' Takip no: ' . $tracking . '.';
    }
    if ($extra !== '') {
        $body .= ' ' . $extra;
    }
    $html = '<p>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>';
    if ($url !== '') {
        $html .= '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Kargo takibini aç</a></p>';
    }
    $html .= '<p>Siparişlerinizi mağaza panelinizden izleyebilirsiniz.</p>';
    return ['title' => $title, 'body' => mb_substr($body, 0, 400), 'html' => $html];
}

function shop_order_set_shipping(array $order, array $in, int $adminId, bool $notify): array
{
    $id = (int) $order['id'];
    $mode = (string) ($order['ship_mode'] ?? 'kargo');
    $allowed = shop_fulfillment_statuses($mode);
    $status = (string) ($in['status'] ?? $order['status']);
    if (!in_array($status, $allowed, true)) {
        throw new RuntimeException('Geçersiz kargo durumu.');
    }
    $carrier = mb_substr(trim((string) ($in['carrier'] ?? '')), 0, 40);
    if ($carrier !== '' && !isset(shop_carriers()[$carrier])) {
        $carrier = 'diger';
    }
    $tracking = mb_substr(trim((string) ($in['tracking'] ?? '')), 0, 80);
    $customUrl = mb_substr(trim((string) ($in['tracking_url'] ?? '')), 0, 255);
    $extra = mb_substr(trim((string) ($in['customer_note'] ?? '')), 0, 400);
    if ($mode === 'dijital') {
        $carrier = '';
        $tracking = '';
        $customUrl = '';
    }
    $url = shop_tracking_url($carrier, $tracking, $customUrl);
    $changed = $status !== (string) $order['status']
        || $carrier !== (string) ($order['ship_carrier'] ?? '')
        || $tracking !== (string) ($order['ship_tracking'] ?? '')
        || $url !== (string) ($order['ship_tracking_url'] ?? '')
        || $extra !== '';

    db()->prepare(
        'UPDATE orders SET status=?, ship_carrier=?, ship_tracking=?, ship_tracking_url=?, ship_updated_at=NOW() WHERE id=?'
    )->execute([$status, $carrier !== '' ? $carrier : null, $tracking !== '' ? $tracking : null, $url !== '' ? $url : null, $id]);

    $eventId = 0;
    $mailed = false;
    if ($changed) {
        try {
            db()->prepare(
                'INSERT INTO order_ship_events (order_id, status, carrier, tracking, tracking_url, message, mail_sent, created_by)
                 VALUES (?,?,?,?,?,?,0,?)'
            )->execute([$id, $status, $carrier !== '' ? $carrier : null, $tracking !== '' ? $tracking : null, $url !== '' ? $url : null, $extra !== '' ? $extra : null, $adminId > 0 ? $adminId : null]);
            $eventId = (int) db()->lastInsertId();
        } catch (Throwable) {
        }
    }

    if ($notify && $changed) {
        $uid = (int) $order['user_id'];
        $email = (string) ($order['email'] ?? '');
        if ($email === '' || empty($order['role'])) {
            $ust = db()->prepare('SELECT email, role, name FROM users WHERE id=?');
            $ust->execute([$uid]);
            $urow = $ust->fetch() ?: [];
            $email = $email !== '' ? $email : (string) ($urow['email'] ?? '');
            $order['role'] = $urow['role'] ?? ($order['role'] ?? '');
            $order['name'] = $order['name'] ?? ($urow['name'] ?? '');
        }
        $fresh = $order;
        $fresh['status'] = $status;
        $fresh['ship_carrier'] = $carrier;
        $fresh['ship_tracking'] = $tracking;
        $fresh['ship_tracking_url'] = $url;
        $copy = shop_ship_notify_copy($fresh, $status, $carrier, $tracking, $url, $extra);
        $link = shop_customer_orders_url($order);
        if (function_exists('notify_user')) {
            notify_user($uid, $copy['title'], $copy['body'], $link);
        }
        if ($email !== '' && function_exists('send_mail')) {
            $html = function_exists('mail_wrap') ? mail_wrap($copy['title'], $copy['html']) : $copy['html'];
            $mailed = send_mail($email, $copy['title'] . ' · ' . (string) ($order['merchant_oid'] ?: '#' . $id), $html, $copy['body']);
        }
        if ($eventId > 0 && $mailed) {
            try {
                db()->prepare('UPDATE order_ship_events SET mail_sent=1 WHERE id=?')->execute([$eventId]);
            } catch (Throwable) {
            }
        }
    }

    return ['changed' => $changed, 'mailed' => $mailed, 'notified' => $notify && $changed];
}

function shop_order_advance(array $order, int $adminId): array
{
    $next = siparis_next_status((string) $order['status']);
    if ($next === null) {
        return ['changed' => false, 'mailed' => false, 'notified' => false];
    }
    return shop_order_set_shipping($order, [
        'status' => $next,
        'carrier' => (string) ($order['ship_carrier'] ?? ''),
        'tracking' => (string) ($order['ship_tracking'] ?? ''),
        'tracking_url' => (string) ($order['ship_tracking_url'] ?? ''),
        'customer_note' => '',
    ], $adminId, true);
}

function ensure_order_admin_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = function_exists('table_columns') ? table_columns('orders') : [];
        if ($cols === []) {
            foreach (db()->query('SHOW COLUMNS FROM orders')->fetchAll() as $row) {
                $cols[(string) $row['Field']] = true;
            }
        }
        foreach ([
            'admin_note' => 'TEXT NULL',
            'ship_carrier' => 'VARCHAR(40) NULL',
            'ship_tracking' => 'VARCHAR(80) NULL',
            'ship_tracking_url' => 'VARCHAR(255) NULL',
            'ship_updated_at' => 'DATETIME NULL',
        ] as $col => $def) {
            if (!isset($cols[$col])) {
                db()->exec('ALTER TABLE orders ADD COLUMN `' . $col . '` ' . $def);
            }
        }
        db()->exec(
            "CREATE TABLE IF NOT EXISTS order_ship_events (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              order_id INT UNSIGNED NOT NULL,
              status VARCHAR(40) NOT NULL,
              carrier VARCHAR(40) NULL,
              tracking VARCHAR(80) NULL,
              tracking_url VARCHAR(255) NULL,
              message VARCHAR(400) NULL,
              mail_sent TINYINT(1) NOT NULL DEFAULT 0,
              created_by INT UNSIGNED NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_ose_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable) {
        // Şema yoksa sessizce geçilir.
    }
}
