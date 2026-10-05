<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\RequestStringBuilder;

class RetrieveActiveInstallmentRequest extends RequestPayload
{
    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("merchant_key", $this->getMerchantKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("merchant_key", $this->getMerchantKey())
            ->getRequestString();
    }
}
