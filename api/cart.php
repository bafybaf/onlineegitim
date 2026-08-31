<?php
require_once __DIR__ . '/../lib/bootstrap.php';

$action = post('action');
if ($action === 'add') {
    $id = (int) post('book_id');
    $st = db()->prepare('SELECT id FROM books WHERE id = ?');
    $st->execute([$id]);
    if ($st->fetch()) {
        cart_set($id, (cart()[$id] ?? 0) + 1);
    }
} elseif ($action === 'set') {
    cart_set((int) post('book_id'), (int) post('qty'));
} elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
} elseif ($action === 'checkout') {
    $u = current_user();
    if (!$u) {
        json_out(['ok' => false, 'error' => 'login'], 401);
    }
    if (!is_shop_role($u['role']) || ($u['status'] ?? '') !== 'aktif') {
        json_out(['ok' => false, 'error' => 'shop_account'], 403);
    }
    $items = cart();
    if (!$items) {
        json_out(['ok' => false, 'error' => 'empty']);
    }
    $ids = array_map('intval', array_keys($items));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM books WHERE id IN ($in)");
    $st->execute($ids);
    $books = [];
    foreach ($st as $b) {
        $books[$b['id']] = $b;
    }
    $basket = [];
    $lines = [];
    $sub = 0;
    foreach ($items as $id => $qty) {
        if (!isset($books[$id])) {
            continue;
        }
        $b = $books[$id];
        $qty = max(1, (int) $qty);
        if (!$b['is_digital'] && (int) $b['stock'] < $qty) {
            json_out(['ok' => false, 'error' => 'stock']);
        }
        $sub += (int) $b['price'] * $qty;
        $b['qty'] = $qty;
        $lines[] = $b;
        $basket[] = [
            'name' => $b['title'],
            'price' => (int) $b['price'],
            'qty' => $qty,
            'book_id' => (int) $b['id'],
            'is_digital' => (int) $b['is_digital'],
        ];
    }
    if (!$basket) {
        json_out(['ok' => false, 'error' => 'empty']);
    }
    $coupon = strtoupper(post('coupon'));
    $hasPhysical = false;
    foreach ($basket as $line) {
        if (empty($line['is_digital'])) {
            $hasPhysical = true;
            break;
        }
    }
    $mode = $hasPhysical ? 'kargo' : 'dijital';
    $ship = ($sub >= 500 || !$hasPhysical) ? 0 : 49;
    $applied = campaign_resolve_for_cart($lines, $sub, $ship, $coupon);
    $sub = max(0, $sub - (int) $applied['discount']);
    $ship = (int) $applied['ship'];
    if ($mode === 'dijital') {
        $ship = 0;
    }
    $coupon = (string) ($applied['code'] ?? $coupon);
    $total = $sub + $ship;
    if ($ship > 0) {
        $basket[] = ['name' => 'Kargo', 'price' => $ship, 'qty' => 1];
    }
    $addr = checkout_resolve_address($u);
    if (($addr['error'] ?? '') !== '' || empty($addr['snap'])) {
        json_out(['ok' => false, 'error' => 'address', 'message' => (string) ($addr['message'] ?? '')], 422);
    }
    $snap = $addr['snap'];
    $payment = payment_create((int) $u['id'], 'kitap', $total, $basket, [
        'ship_mode' => $mode,
        'coupon' => $coupon ?: null,
        'address_id' => (int) ($snap['id'] ?? 0),
        'address' => $snap,
    ]);
    $_SESSION['cart'] = [];
    json_out(['ok' => true, 'pay_url' => payment_checkout_url($payment), 'count' => 0]);
}

json_out(['ok' => true, 'count' => cart_count()]);
