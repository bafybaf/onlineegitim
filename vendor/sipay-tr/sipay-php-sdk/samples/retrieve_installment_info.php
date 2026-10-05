<?php

declare(strict_types=1);

use Sipay\Requests\RetrieveInstallmentInfoRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

/** @var RetrieveInstallmentInfoRequest $request */
$request = $sipay->createRequest(RetrieveInstallmentInfoRequest::class);
$request->setCurrencyCode(Sipay\Enums\CurrencyCode::TRY)
    ->setCreditCart('540667')
    ->setAmount(100)
;

# make request
$installmentInfo = $sipay->installmentInfoResource()->retrieve($request);

# print result
dump($installmentInfo);

$request->setCurrencyCode(Sipay\Enums\CurrencyCode::TRY)
    ->setCreditCart('111111')
    ->setAmount(100)
;

# make request
$installmentInfo = $sipay->installmentInfoResource()->retrieve($request);

# print result
dump($installmentInfo);
