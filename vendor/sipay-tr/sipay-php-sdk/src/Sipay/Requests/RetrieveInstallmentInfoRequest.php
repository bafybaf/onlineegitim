<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\RequestStringBuilder;

class RetrieveInstallmentInfoRequest extends RequestPayload
{
    private $creditCart;

    private $amount;

    private $currencyCode;

    public function getCreditCart(): string
    {
        return $this->creditCart;
    }

    public function setCreditCart(string $creditCart): self
    {
        $this->creditCart = $creditCart;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

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
            ->add("credit_card", $this->getCreditCart())
            ->add("amount", $this->getAmount())
            ->add("currency_code", $this->getCurrencyCode())
            ->add("merchant_key", $this->getMerchantKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->appendSuper(parent::toPKIRequestString())
            ->append("credit_card", $this->getCreditCart())
            ->append("amount", $this->getAmount())
            ->append("currency_code", $this->getCurrencyCode())
            ->getRequestString();
    }
}
