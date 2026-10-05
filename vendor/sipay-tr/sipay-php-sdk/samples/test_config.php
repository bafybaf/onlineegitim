<?php

declare(strict_types=1);

use Sipay\Sipay;
use Sipay\SipayOptions;

require __DIR__ . '/../vendor/autoload.php';

define('TEST_API_BASE_URL', 'https://provisioning.sipay.com.tr/ccpayment');
define('TEST_MERCHANT_KEY', '$2y$10$HmRgYosneqcwHj.UH7upGuyCZqpQ1ITgSMj9Vvxn.t6f.Vdf2SQFO');

$sipayOptions = new SipayOptions(
    '6d4a7e9374a76c15260fcc75e315b0b9',
    'b46a67571aa1e7ef5641dc3fa6f1712a',
    TEST_MERCHANT_KEY,
    '18309',
    TEST_API_BASE_URL
);
$sipay = new Sipay($sipayOptions);
