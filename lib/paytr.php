<?php
function paytr_configured(): bool
{
    return setting('paytr_merchant_id') !== ''
        && setting('paytr_merchant_key') !== ''
        && setting('paytr_merchant_salt') !== '';
}

function paytr_client_ip(): string
{
    $override = trim(setting('paytr_public_ip'));
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (str_contains((string) $ip, ',')) {
        $ip = trim(explode(',', (string) $ip)[0]);
    }
    $ip = (string) $ip;
    $local = in_array($ip, ['127.0.0.1', '::1', 'localhost'], true) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.');
    if ($override !== '' && $local) {
        return $override;
    }
    if ($local && $override === '') {
        $fetched = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 3]]));
        if (is_string($fetched) && filter_var(trim($fetched), FILTER_VALIDATE_IP)) {
            return trim($fetched);
        }
    }
    return $ip;
}

function paytr_basket(array $lines): string
{
    $rows = [];
    foreach ($lines as $line) {
        $rows[] = [
            (string) $line['name'],
            number_format((int) $line['price'], 2, '.', ''),
            (int) $line['qty'],
        ];
    }
    return base64_encode(json_encode($rows, JSON_UNESCAPED_UNICODE));
}

function paytr_get_token(array $payment, array $user, array $basketLines): array
{
    if (!paytr_configured()) {
        return ['ok' => false, 'error' => 'PayTR mağaza bilgileri admin panelden girilmedi.'];
    }
    $merchantId = setting('paytr_merchant_id');
    $merchantKey = setting('paytr_merchant_key');
    $merchantSalt = setting('paytr_merchant_salt');
    $email = (string) $user['email'];
    $paymentAmount = (string) ((int) $payment['total'] * 100);
    $merchantOid = (string) $payment['merchant_oid'];
    $snap = function_exists('payment_address_snapshot') ? payment_address_snapshot($payment) : [];
    $userName = mb_substr((string) ($snap['name'] ?? $user['name']), 0, 60);
    $userPhone = preg_replace('/\D+/', '', (string) ($snap['phone'] ?? $user['phone'] ?? '')) ?: '5555555555';
    $userAddress = function_exists('address_paytr_line') && $snap
        ? address_paytr_line($snap)
        : (mb_substr((string) ($user['city'] ?? 'Türkiye'), 0, 400) ?: 'Türkiye');
    $userBasket = paytr_basket($basketLines);
    $userIp = paytr_client_ip();
    $timeoutLimit = '30';
    $debugOn = setting_bool('paytr_debug', true) ? '1' : '0';
    $testMode = setting_bool('paytr_test_mode', true) ? '1' : '0';
    $noInstallment = setting_bool('paytr_no_installment') ? '1' : '0';
    $maxInstallment = setting('paytr_max_installment', '0') ?: '0';
    $currency = 'TL';
    $hashStr = $merchantId . $userIp . $merchantOid . $email . $paymentAmount . $userBasket . $noInstallment . $maxInstallment . $currency . $testMode;
    $paytrToken = base64_encode(hash_hmac('sha256', $hashStr . $merchantSalt, $merchantKey, true));
    $post = [
        'merchant_id' => $merchantId,
        'user_ip' => $userIp,
        'merchant_oid' => $merchantOid,
        'email' => $email,
        'payment_amount' => $paymentAmount,
        'paytr_token' => $paytrToken,
        'user_basket' => $userBasket,
        'debug_on' => $debugOn,
        'no_installment' => $noInstallment,
        'max_installment' => $maxInstallment,
        'user_name' => $userName,
        'user_address' => $userAddress,
        'user_phone' => $userPhone,
        'merchant_ok_url' => app_public_url(seo_odeme_sonuc_path('ok', $merchantOid)),
        'merchant_fail_url' => app_public_url(seo_odeme_sonuc_path('hata', $merchantOid)),
        'timeout_limit' => $timeoutLimit,
        'currency' => $currency,
        'test_mode' => $testMode,
        'lang' => 'tr',
    ];
    if (setting_bool('paytr_iframe_v2', true)) {
        $post['iframe_v2'] = '1';
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://www.paytr.com/odeme/api/get-token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => setting_bool('paytr_ssl_verify', true),
    ]);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($errno) {
        return ['ok' => false, 'error' => 'PayTR bağlantı hatası: ' . $err];
    }
    $json = json_decode((string) $result, true);
    if (!is_array($json) || ($json['status'] ?? '') !== 'success' || empty($json['token'])) {
        $reason = is_array($json) ? (string) ($json['reason'] ?? json_encode($json, JSON_UNESCAPED_UNICODE)) : (string) $result;
        return ['ok' => false, 'error' => $reason !== '' ? $reason : 'PayTR token alınamadı.'];
    }
    return ['ok' => true, 'token' => (string) $json['token']];
}

function paytr_verify_callback(array $post): bool
{
    $oid = (string) ($post['merchant_oid'] ?? '');
    $status = (string) ($post['status'] ?? '');
    $totalAmount = (string) ($post['total_amount'] ?? '');
    $hash = (string) ($post['hash'] ?? '');
    if ($oid === '' || $hash === '') {
        return false;
    }
    $expected = base64_encode(hash_hmac(
        'sha256',
        $oid . setting('paytr_merchant_salt') . $status . $totalAmount,
        setting('paytr_merchant_key'),
        true
    ));
    return hash_equals($expected, $hash);
}
