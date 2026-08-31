<?php

function ensure_iyzico_schema(): void
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
            db()->exec('ALTER TABLE payments ADD COLUMN gateway_token VARCHAR(128) NULL');
        }
        foreach ([
            'iyzico_enabled' => '0',
            'iyzico_api_key' => '',
            'iyzico_secret_key' => '',
            'iyzico_sandbox' => '1',
        ] as $k => $v) {
            db()->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?,?)')->execute([$k, $v]);
        }
    } catch (Throwable) {
        $done = false;
    }
}

function iyzico_configured(): bool
{
    ensure_iyzico_schema();
    return setting_bool('iyzico_enabled')
        && setting('iyzico_api_key') !== ''
        && setting('iyzico_secret_key') !== ''
        && class_exists(\Iyzipay\Options::class);
}

function iyzico_options(): ?\Iyzipay\Options
{
    if (!iyzico_configured()) {
        return null;
    }
    $opt = new \Iyzipay\Options();
    $opt->setApiKey(setting('iyzico_api_key'));
    $opt->setSecretKey(setting('iyzico_secret_key'));
    $opt->setBaseUrl(setting_bool('iyzico_sandbox', true)
        ? 'https://sandbox-api.iyzipay.com'
        : 'https://api.iyzipay.com');
    return $opt;
}

function iyzico_money(int $tl): string
{
    return number_format(max(0, $tl), 2, '.', '');
}

function iyzico_phone(?string $raw): string
{
    $d = preg_replace('/\D+/', '', (string) $raw);
    if (str_starts_with($d, '90') && strlen($d) >= 12) {
        return '+' . substr($d, 0, 12);
    }
    if (str_starts_with($d, '0') && strlen($d) >= 11) {
        return '+90' . substr($d, 1, 10);
    }
    if (strlen($d) === 10) {
        return '+90' . $d;
    }
    return '+905350000000';
}

function iyzico_split_name(string $full): array
{
    $parts = preg_split('/\s+/', trim($full)) ?: [];
    $first = $parts[0] ?? 'Musteri';
    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;
    return [$first, $last];
}

function payment_checkout_url(array $payment): string
{
    $oid = (string) ($payment['merchant_oid'] ?? '');
    if (($payment['status'] ?? '') === 'odendi') {
        return odeme_sonuc_url('ok', $oid);
    }
    if ((int) ($payment['total'] ?? 0) < 1) {
        $paid = payment_settle_now($payment);
        return odeme_sonuc_url('ok', (string) ($paid['merchant_oid'] ?? $oid));
    }
    if (iyzico_configured()) {
        return odeme_url($oid);
    }
    $paid = payment_settle_now($payment);
    return odeme_sonuc_url('ok', (string) ($paid['merchant_oid'] ?? $oid));
}

function iyzico_init_checkout(array $payment, array $user): array
{
    $opt = iyzico_options();
    if (!$opt) {
        return ['ok' => false, 'error' => 'iyzico ayarlı değil'];
    }
    $oid = (string) $payment['merchant_oid'];
    $total = (int) $payment['total'];
    $basket = payment_basket($payment);
    if ($total < 1 || !$basket) {
        return ['ok' => false, 'error' => 'Sepet boş'];
    }
    $snap = function_exists('payment_address_snapshot') ? payment_address_snapshot($payment) : [];
    [$first, $last] = iyzico_split_name((string) ($snap['name'] ?? $user['name'] ?? 'Musteri'));
    $phone = iyzico_phone((string) ($snap['phone'] ?? $user['phone'] ?? ''));
    $city = trim((string) ($snap['city'] ?? $user['city'] ?? 'Istanbul')) ?: 'Istanbul';
    $line = trim((string) ($snap['line'] ?? ''));
    if ($line === '') {
        $line = $city;
    }
    $ip = function_exists('paytr_client_ip') ? paytr_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $created = (string) ($user['created_at'] ?? date('Y-m-d H:i:s'));
    $kind = (string) ($payment['kind'] ?? 'kitap');
    $group = in_array($kind, ['uyelik_ders', 'program', 'uyelik_magaza'], true)
        ? \Iyzipay\Model\PaymentGroup::VIRTUAL
        : \Iyzipay\Model\PaymentGroup::PRODUCT;

    $req = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
    $req->setLocale(\Iyzipay\Model\Locale::TR);
    $req->setConversationId($oid);
    $req->setPrice(iyzico_money($total));
    $req->setPaidPrice(iyzico_money($total));
    $req->setCurrency(\Iyzipay\Model\Currency::TL);
    $req->setBasketId($oid);
    $req->setPaymentGroup($group);
    $req->setCallbackUrl(app_public_url('api/iyzico-callback.php'));
    $req->setEnabledInstallments([2, 3, 6, 9]);

    $buyer = new \Iyzipay\Model\Buyer();
    $buyer->setId('U' . (int) $user['id']);
    $buyer->setName($first);
    $buyer->setSurname($last);
    $buyer->setGsmNumber($phone);
    $buyer->setEmail((string) $user['email']);
    $buyer->setIdentityNumber('74300864791');
    $buyer->setLastLoginDate(date('Y-m-d H:i:s'));
    $buyer->setRegistrationDate($created);
    $buyer->setRegistrationAddress($line);
    $buyer->setIp($ip);
    $buyer->setCity($city);
    $buyer->setCountry('Turkey');
    $buyer->setZipCode('34000');
    $req->setBuyer($buyer);

    $addr = new \Iyzipay\Model\Address();
    $addr->setContactName(trim($first . ' ' . $last));
    $addr->setCity($city);
    $addr->setCountry('Turkey');
    $addr->setAddress($line);
    $addr->setZipCode('34000');
    $req->setShippingAddress($addr);
    $req->setBillingAddress($addr);

    $items = [];
    $sum = 0;
    foreach (array_values($basket) as $i => $row) {
        $qty = max(1, (int) ($row['qty'] ?? 1));
        $unit = (int) ($row['price'] ?? 0);
        $lineTotal = $unit * $qty;
        if ($lineTotal < 1) {
            continue;
        }
        $sum += $lineTotal;
        $item = new \Iyzipay\Model\BasketItem();
        $item->setId('BI' . ($i + 1));
        $item->setName(mb_substr((string) ($row['name'] ?? 'Kalem'), 0, 120));
        $virtual = !empty($row['is_digital']) || $kind !== 'kitap' || (($row['name'] ?? '') === 'Kargo');
        $item->setItemType($virtual && ($row['name'] ?? '') !== 'Kargo'
            ? \Iyzipay\Model\BasketItemType::VIRTUAL
            : \Iyzipay\Model\BasketItemType::PHYSICAL);
        $item->setCategory1($kind === 'kitap' ? 'Kitap' : 'Egitim');
        $item->setPrice(iyzico_money($lineTotal));
        $items[] = $item;
    }
    if (!$items) {
        return ['ok' => false, 'error' => 'Sepet kalemi yok'];
    }
    if ($sum !== $total) {
        $req->setPrice(iyzico_money($sum));
        $req->setPaidPrice(iyzico_money($total));
    }
    $req->setBasketItems($items);

    try {
        $form = \Iyzipay\Model\CheckoutFormInitialize::create($req, $opt);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    if ($form->getStatus() !== 'success') {
        return ['ok' => false, 'error' => $form->getErrorMessage() ?: 'iyzico formu açılamadı'];
    }
    $token = (string) $form->getToken();
    try {
        db()->prepare('UPDATE payments SET provider = ?, gateway_token = ? WHERE id = ?')
            ->execute(['iyzico', $token, (int) $payment['id']]);
    } catch (Throwable) {
    }
    return [
        'ok' => true,
        'token' => $token,
        'html' => (string) $form->getCheckoutFormContent(),
        'page' => (string) $form->getPaymentPageUrl(),
    ];
}

function iyzico_complete(string $token): array
{
    $opt = iyzico_options();
    $token = trim($token);
    if (!$opt || $token === '') {
        return ['ok' => false, 'error' => 'token'];
    }
    $req = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
    $req->setLocale(\Iyzipay\Model\Locale::TR);
    $req->setToken($token);
    try {
        $form = \Iyzipay\Model\CheckoutForm::retrieve($req, $opt);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
    $oid = (string) $form->getConversationId();
    $payment = $oid !== '' ? payment_by_oid($oid) : null;
    if (!$payment) {
        try {
            $st = db()->prepare('SELECT * FROM payments WHERE gateway_token = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$token]);
            $payment = $st->fetch() ?: null;
        } catch (Throwable) {
            $payment = null;
        }
    }
    if (!$payment) {
        return ['ok' => false, 'error' => 'Ödeme kaydı yok'];
    }
    $payStatus = strtolower((string) $form->getPaymentStatus());
    $apiStatus = strtolower((string) $form->getStatus());
    if ($apiStatus === 'success' && in_array($payStatus, ['success', 'success_with_warning'], true)) {
        $paid = (int) round((float) $form->getPaidPrice());
        if ($paid > 0 && $paid < (int) $payment['total']) {
            payment_fail($payment, 'Tutar uyuşmadı');
            return ['ok' => false, 'payment' => $payment, 'error' => 'Tutar uyuşmadı'];
        }
        payment_fulfill($payment);
        $fresh = payment_by_oid((string) $payment['merchant_oid']);
        return ['ok' => true, 'payment' => $fresh ?: $payment];
    }
    $reason = $form->getErrorMessage() ?: 'Ödeme alınamadı';
    payment_fail($payment, $reason);
    return ['ok' => false, 'payment' => $payment, 'error' => $reason];
}

ensure_iyzico_schema();
