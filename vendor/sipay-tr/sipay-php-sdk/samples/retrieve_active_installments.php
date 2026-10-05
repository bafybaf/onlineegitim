<?php

declare(strict_types=1);

use Sipay\Requests\RetrieveActiveInstallmentRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

# create request class
$request = $sipay->createRequest(RetrieveActiveInstallmentRequest::class);

# make request
$installmentInfo = $sipay->activeInstallmentResource()->retrieve($request);

# print result
dump($installmentInfo);
