<?php

declare(strict_types=1);

namespace Sipay\Utils;

class HashKey
{
    public static function generateHashKey(array $parts, string $app_secret): string
    {

        //$data = $total . '|' . $installment . '|' . $currency_code . '|' . $merchant_key . '|' . $invoice_id;
        $data = implode("|", $parts);

        $iv = substr(sha1((string)mt_rand()), 0, 16);
        $password = sha1($app_secret);

        $salt = substr(sha1((string)mt_rand()), 0, 4);
        $saltWithPassword = hash('sha256', $password . $salt);

        $encrypted = openssl_encrypt(
            "$data",
            'aes-256-cbc',
            "$saltWithPassword",
            0,
            $iv
        );
        $msg_encrypted_bundle = "$iv:$salt:$encrypted";
        $msg_encrypted_bundle = str_replace('/', '__', $msg_encrypted_bundle);

        return $msg_encrypted_bundle;
    }

    public static function validateHashKey(string $hashKey, string $secretKey): array
    {
        $status = $currencyCode = "";
        $total = $invoiceId = $orderId = 0;

        if (!empty($hashKey)) {
            $hashKey = str_replace('__', '/', $hashKey);
            $password = sha1($secretKey);

            $components = explode(':', $hashKey);
            if (count($components) > 2) {
                $iv = $components[0] ?? "";
                $salt = $components[1] ?? "";
                $salt = hash('sha256', $password . $salt);
                $encryptedMsg = $components[2] ?? "";

                $decryptedMsg = openssl_decrypt($encryptedMsg, 'aes-256-cbc', $salt, 0, $iv);

                if (is_string($decryptedMsg) && strpos($decryptedMsg, '|') !== false) {
                    $array = explode('|', $decryptedMsg);
                    $status = $array[0] ?? 0;
                    $total = $array[1] ?? 0;
                    $invoiceId = $array[2] ?? '0';
                    $orderId = $array[3] ?? 0;
                    $currencyCode = $array[4] ?? '';
                }
            }
        }

        return [$status, $total, $invoiceId, $orderId, $currencyCode];
    }
}
