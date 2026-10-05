<?php

declare(strict_types=1);

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */
$cardToken = 'f04cc117d3fbfb149c7cff234cdf5fa7';
$customerNumber = 5070;

/** @var Sipay\Requests\DeleteCardRequest $request */
$request = $sipay->createRequest(Sipay\Requests\DeleteCardRequest::class);

$request->setCardToken($cardToken)
    ->setCustomerNumber($customerNumber);

$creditCard = $sipay->cardResource()->delete($request);

# print result
dump($creditCard);
