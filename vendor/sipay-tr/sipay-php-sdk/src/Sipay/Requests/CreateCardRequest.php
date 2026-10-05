<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;
use Sipay\RequestStringBuilder;

class CreateCardRequest extends RequestPayload implements WithHashKey
{
    use InteractsHashKey;

    private string $cardHolderName;

    private int $cardNumber;

    private int $expiryMonth;

    private int $expiryYear;

    private int $customerNumber;

    private string $customerName;

    private string $customerEmail;

    private string $customerPhone;

    public function getCardHolderName(): string
    {
        return $this->cardHolderName;
    }

    public function setCardHolderName(string $cardHolderName): self
    {
        $this->cardHolderName = $cardHolderName;

        return $this;
    }

    public function getCardNumber(): int
    {
        return $this->cardNumber;
    }

    public function setCardNumber(int $cardNumber): self
    {
        $this->cardNumber = $cardNumber;

        return $this;
    }

    public function getExpiryMonth(): int
    {
        return $this->expiryMonth;
    }

    public function setExpiryMonth(int $expiryMonth): self
    {
        $this->expiryMonth = $expiryMonth;

        return $this;
    }

    public function getExpiryYear(): int
    {
        return $this->expiryYear;
    }

    public function setExpiryYear(int $expiryYear): self
    {
        $this->expiryYear = $expiryYear;

        return $this;
    }

    public function getCustomerNumber(): int
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber(int $customerNumber): self
    {
        $this->customerNumber = $customerNumber;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getCustomerPhone(): string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(string $customerPhone): self
    {
        $this->customerPhone = $customerPhone;

        return $this;
    }

    public function generateHashKeyParts(): array
    {
        return [
            $this->getMerchantKey(),
            $this->getCustomerNumber(),
            $this->getCardHolderName(),
            $this->getCardNumber(),
            $this->getExpiryMonth(),
            $this->getExpiryYear(),
        ];
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("merchant_key", $this->getMerchantKey())
            ->add("card_holder_name", $this->getCardHolderName())
            ->add("card_number", $this->getCardNumber())
            ->add("expiry_month", $this->getExpiryMonth())
            ->add("expiry_year", $this->getExpiryYear())
            ->add("customer_number", $this->getCustomerNumber())
            ->add("customer_name", $this->getCustomerName())
            ->add("customer_email", $this->getCustomerEmail())
            ->add("customer_phone", $this->getCustomerPhone())
            ->add("hash_key", $this->getHashKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("merchant_key", $this->getMerchantKey())
            ->append("card_holder_name", $this->getCardHolderName())
            ->append("card_number", $this->getCardNumber())
            ->append("expiry_month", $this->getExpiryMonth())
            ->append("expiry_year", $this->getExpiryYear())
            ->append("customer_number", $this->getCustomerNumber())
            ->append("customer_name", $this->getCustomerName())
            ->append("customer_email", $this->getCustomerEmail())
            ->append("customer_phone", $this->getCustomerPhone())
            ->append("hash_key", $this->getHashKey())
            ->getRequestString();
    }
}
