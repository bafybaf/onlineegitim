<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\RequestStringBuilder;

class RetrieveCommissionRequest extends RequestPayload
{
    private $commissionBy;

    private $currencyCode;

    public function getCommissionBy(): string
    {
        return $this->commissionBy;
    }

    public function setCommissionBy(string $commissionBy): self
    {
        $this->commissionBy = $commissionBy;

        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function getJsonObject()
    {
        return JsonBuilder::fromJsonObject(parent::getJsonObject())
            ->add("currency_code", $this->getCurrencyCode())
            ->add("commission_by", $this->getCommissionBy())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->appendSuper(parent::toPKIRequestString())
            ->append("currency_code", $this->getCurrencyCode())
            ->append("commission_by", $this->getCommissionBy())
            ->getRequestString();
    }
}
