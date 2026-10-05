<?php

declare(strict_types=1);

use Sipay\Requests\RetrieveCommissionRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

/** @var RetrieveCommissionRequest $request */
$request = $sipay->createRequest(RetrieveCommissionRequest::class);
$request->setCurrencyCode(Sipay\Enums\CurrencyCode::TRY)
    ->setCommissionBy(Sipay\Enums\CommissionBy::USER)
;

# make request
$commissionInfo = $sipay->commissionInfoResource()->retrieve($request);

# print result
dump($commissionInfo);
