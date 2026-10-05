<?php

function ensure_sipay_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $cols = function_exists('table_columns') ? table_columns('payments') : [];
        if ($cols && !isset($cols['provider'])) {
            db()->exec("ALTER TABLE payments ADD COLUMN provider VARCHAR(20) NULL");
        }
        if ($cols && !isset($cols['gateway_token'])) {
            db()->exec('ALTER TABLE payments ADD COLUMN gateway_token VARCHAR(255) NULL');
        }
        foreach ([
            'sipay_api_key' => '',
            'sipay_api_secret' => '',
            'sipay_merchant_key' => '',
            'sipay_merchant_id' => '',
            'sipay_test_mode' => '1',
            'sipay_max_installment' => '12',
            'sipay_sale_webhook_key' => '',
            'sipay_public_ip' => '',
        ] as $k => $v) {
            db()->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?,?)')->execute([$k, $v]);
        }
    } catch (Throwable) {
        $done = false;
    }
}

function sipay_configured(): bool
{
    ensure_sipay_schema();
    return setting('sipay_api_key') !== ''
        && setting('sipay_api_secret') !== ''
        && setting('sipay_merchant_key') !== ''
        && setting('sipay_merchant_id') !== ''
        && class_exists(\Sipay\Sipay::class);
}

function sipay_callback_url(): string
{
    return app_public_url('api/sipay-callback.php');
}

function sipay_base_url(): string
{
    return setting_bool('sipay_test_mode', true)
        ? 'https://provisioning.sipay.com.tr/ccpayment'
        : 'https://app.sipay.com.tr/ccpayment';
}

function sipay_client(): ?\Sipay\Sipay
{
    if (!sipay_configured()) {
        return null;
    }
    $opt = new \Sipay\SipayOptions(
        setting('sipay_api_key'),
        setting('sipay_api_secret'),
        setting('sipay_merchant_key'),
        setting('sipay_merchant_id'),
        sipay_base_url()
    );
    return new \Sipay\Sipay($opt);
}

function payment_client_ip(): string
{
    $override = trim(setting('sipay_public_ip'));
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (str_contains((string) $ip, ',')) {
        $ip = trim(explode(',', (string) $ip)[0]);
    }
    $ip = (string) $ip;
    $local = in_array($ip, ['127.0.0.1', '::1', 'localhost'], true) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.');
    if ($override !== '' && $local) {
        return $override;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return $override !== '' ? $override : '203.0.113.10';
}

function sipay_split_name(string $full): array
{
    $parts = preg_split('/\s+/', trim($full)) ?: [];
    $first = $parts[0] ?? 'Musteri';
    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;
    return [$first, $last];
}

function sipay_phone(?string $raw): string
{
    $d = preg_replace('/\D+/', '', (string) $raw);
    if (str_starts_with((string) $d, '90') && strlen((string) $d) >= 12) {
        return substr((string) $d, 0, 12);
    }
    if (str_starts_with((string) $d, '0') && strlen((string) $d) >= 11) {
        return '90' . substr((string) $d, 1, 10);
    }
    if (strlen((string) $d) === 10) {
        return '90' . $d;
    }
    return '905555555555';
}

function sipay_payload(array $src = []): array
{
    $raw = file_get_contents('php://input');
    $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $out = array_merge($_GET, $_POST, is_array($json) ? $json : [], $src);
    return is_array($out) ? $out : [];
}

function sipay_invoice_id_from(array $data): string
{
    foreach (['invoice_id', 'invoiceId', 'merchant_oid', 'order_id'] as $k) {
        $v = trim((string) ($data[$k] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

function sipay_status_paid($status): bool
{
    $s = strtolower(trim((string) $status));
    return in_array($s, ['1', 'success', 'completed', 'complete', 'odendi'], true);
}

function sipay_status_failed($status): bool
{
    $s = strtolower(trim((string) $status));
    return in_array($s, ['0', '2', '4', 'failed', 'fail', 'canceled', 'cancelled', 'error'], true);
}

function sipay_create_link(array $payment, array $user): array
{
    $sipay = sipay_client();
    if (!$sipay) {
        return ['ok' => false, 'error' => 'Sipay mağaza bilgileri admin panelden girilmedi.'];
    }
    $oid = (string) ($payment['merchant_oid'] ?? '');
    $total = (int) ($payment['total'] ?? 0);
    $basket = payment_basket($payment);
    if ($oid === '' || $total < 1) {
        return ['ok' => false, 'error' => 'Ödeme tutarı geçersiz.'];
    }
    $snap = function_exists('payment_address_snapshot') ? payment_address_snapshot($payment) : [];
    [$first, $last] = sipay_split_name((string) ($snap['name'] ?? $user['name'] ?? 'Musteri'));
    $phone = sipay_phone((string) ($snap['phone'] ?? $user['phone'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'musteri@onlineilahiyat.com';
    }
    $city = trim((string) ($snap['city'] ?? $user['city'] ?? 'Istanbul')) ?: 'Istanbul';
    $line = trim((string) ($snap['line'] ?? $city));
    $district = trim((string) ($snap['district'] ?? $city));
    $kind = function_exists('payment_kind_label') ? payment_kind_label((string) ($payment['kind'] ?? '')) : 'Odeme';

    $items = [];
    $sum = 0;
    foreach (array_values($basket) as $row) {
        $qty = max(1, (int) ($row['qty'] ?? 1));
        $unit = (int) ($row['price'] ?? 0);
        $lineTotal = $unit * $qty;
        if ($lineTotal < 1) {
            continue;
        }
        $sum += $lineTotal;
        $item = $sipay->createModel(\Sipay\Models\PaymentLinkInvoiceItem::class);
        $item->setName(mb_substr((string) ($row['name'] ?? $kind), 0, 120))
            ->setPrice($unit)
            ->setQuantity($qty)
            ->setDescription(mb_substr((string) ($row['name'] ?? $kind), 0, 160));
        $items[] = $item;
    }
    if (!$items || $sum !== $total) {
        $item = $sipay->createModel(\Sipay\Models\PaymentLinkInvoiceItem::class);
        $item->setName($kind)->setPrice($total)->setQuantity(1)->setDescription($kind);
        $items = [$item];
        $sum = $total;
    }
    $invoiceTotal = $total;
    $maxInst = (string) max(1, min(12, (int) setting('sipay_max_installment', '12')));

    try {
        $invoice = $sipay->createModel(\Sipay\Models\PaymentLinkInvoice::class);
        $invoice->setInvoiceDescription($kind)
            ->setInvoiceId($oid)
            ->setTotal($invoiceTotal)
            ->setItems($items)
            ->setMaxInstallment($maxInst)
            ->setCancelUrl(app_public_url(seo_odeme_sonuc_path('hata', $oid)))
            ->setReturnUrl(app_public_url(seo_odeme_sonuc_path('ok', $oid)))
            ->setBillAddress1(mb_substr($line !== '' ? $line : $city, 0, 200))
            ->setBillAddress2($district)
            ->setBillCity($city)
            ->setBillPostcode('34000')
            ->setBillState($district !== '' ? $district : $city)
            ->setBillCountry('TURKEY')
            ->setBillEmail($email)
            ->setBillPhone($phone)
            ->setOrderType(0)
            ->setCurrencyCode(\Sipay\Enums\CurrencyCode::TRY);
        $hook = trim(setting('sipay_sale_webhook_key'));
        if ($hook !== '') {
            $invoice->setSaleWebHookKey($hook);
        }
        $request = $sipay->createRequest(\Sipay\Requests\CreatePaymentLinkRequest::class);
        $request->setName($first)->setSurname($last)->setInvoice($invoice);
        $linkRes = $sipay->paymentLinkResource()->generate($request);
        $link = trim((string) $linkRes->getLink());
        if ($link === '') {
            $hint = trim((string) ($linkRes->getSuccessMessage() ?: $linkRes->getStatusDescription() ?: ''));
            return ['ok' => false, 'error' => $hint !== '' ? $hint : 'Sipay ödeme bağlantısı alınamadı.'];
        }
        try {
            db()->prepare('UPDATE payments SET provider = ?, gateway_token = ? WHERE id = ?')
                ->execute(['sipay', $link, (int) $payment['id']]);
        } catch (Throwable) {
        }
        return ['ok' => true, 'link' => $link];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function sipay_check_invoice(string $invoiceId): array
{
    $sipay = sipay_client();
    if (!$sipay || $invoiceId === '') {
        return ['ok' => false, 'paid' => false, 'failed' => false, 'error' => 'Sipay ayarlı değil'];
    }
    try {
        $req = $sipay->createRequest(\Sipay\Requests\CheckTransactionStatusRequest::class);
        $req->setInvoiceId($invoiceId)->setIncludePendingStatus(true);
        $res = $sipay->transactionStatusResource()->retrieve($req);
        $status = $res->getTransactionStatus();
        return [
            'ok' => true,
            'paid' => sipay_status_paid($status),
            'failed' => sipay_status_failed($status),
            'status' => $status,
            'message' => (string) $res->getMessage(),
            'amount' => $res->getTransactionAmount(),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'paid' => false, 'failed' => false, 'error' => $e->getMessage()];
    }
}

function sipay_hash_result(array $data): ?array
{
    $hash = trim((string) ($data['hash_key'] ?? $data['hash'] ?? ''));
    $secret = setting('sipay_api_secret');
    if ($hash === '' || $secret === '' || !class_exists(\Sipay\Utils\HashKey::class)) {
        return null;
    }
    try {
        [$status, $total, $invoiceId] = \Sipay\Utils\HashKey::validateHashKey($hash, $secret);
        if ((string) $invoiceId === '' || (string) $invoiceId === '0') {
            return null;
        }
        return [
            'status' => $status,
            'total' => $total,
            'invoice_id' => (string) $invoiceId,
        ];
    } catch (Throwable) {
        return null;
    }
}

function sipay_apply_result(array $payment, bool $paid, bool $failed, string $reason = ''): array
{
    if ($paid) {
        payment_fulfill($payment);
        $fresh = payment_by_oid((string) $payment['merchant_oid']);
        return ['ok' => true, 'payment' => $fresh ?: $payment];
    }
    if ($failed) {
        payment_fail($payment, $reason !== '' ? $reason : 'Ödeme alınamadı');
        $fresh = payment_by_oid((string) $payment['merchant_oid']);
        return ['ok' => false, 'payment' => $fresh ?: $payment, 'error' => $reason];
    }
    return ['ok' => false, 'payment' => $payment, 'pending' => true];
}

function sipay_sync_payment(array $payment, array $data = []): array
{
    $oid = (string) ($payment['merchant_oid'] ?? '');
    if ($oid === '') {
        return ['ok' => false, 'error' => 'oid'];
    }
    if (($payment['status'] ?? '') === 'odendi') {
        return ['ok' => true, 'payment' => $payment];
    }
    $hash = sipay_hash_result($data);
    if ($hash && (string) $hash['invoice_id'] === $oid && sipay_status_paid($hash['status'])) {
        $paidAmt = (int) round((float) $hash['total']);
        if ($paidAmt > 0 && $paidAmt + 1 < (int) $payment['total']) {
            return sipay_apply_result($payment, false, true, 'Tutar uyuşmadı');
        }
        return sipay_apply_result($payment, true, false);
    }
    $check = sipay_check_invoice($oid);
    if (!empty($check['paid'])) {
        return sipay_apply_result($payment, true, false);
    }
    if (!empty($check['failed'])) {
        return sipay_apply_result($payment, false, true, (string) ($check['message'] ?? 'Ödeme alınamadı'));
    }
    if ($hash && (string) $hash['invoice_id'] === $oid && sipay_status_failed($hash['status'])) {
        return sipay_apply_result($payment, false, true, 'Ödeme başarısız');
    }
    return ['ok' => false, 'payment' => $payment, 'pending' => true, 'error' => $check['error'] ?? ''];
}

ensure_sipay_schema();
