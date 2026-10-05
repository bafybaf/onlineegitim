<?php

declare(strict_types=1);

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

/** @var Sipay\Requests\RetrieveCardListRequest $request */
$request = $sipay->createRequest(Sipay\Requests\RetrieveCardListRequest::class);
$request->setCustomerNumber(5070);

$cardList = $sipay->cardListResource()->retrieve($request);

# print result
dump($cardList);
