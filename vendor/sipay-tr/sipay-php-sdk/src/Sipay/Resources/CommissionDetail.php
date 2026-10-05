<?php

declare(strict_types=1);

namespace Sipay\Resources;

/*
 * TODO: merchantCommissionDisabled ve userCommissionDisabled propları eklenmeli mi?
 * ref: https://apidocs.sipay.com.tr/#tag/token/paths/~1api~1commissions/post Response schema => data açıklamasına bak.
 */
class CommissionDetail
{
    private string $title;

    private ?string $cardProgram;

    private ?float $merchantCommissionPercentage;

    private ?float $merchantCommissionFixed;

    private ?float $userCommissionFixed;

    private ?float $userCommissionPercentage;

    private string $currencyCode;

    private int $installment;

    private int $posId;

    private string $getposCardProgram;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getCardProgram(): string
    {
        return $this->cardProgram;
    }

    public function setCardProgram(?string $cardProgram): self
    {
        $this->cardProgram = $cardProgram;

        return $this;
    }

    public function getMerchantCommissionPercentage(): float
    {
        return $this->merchantCommissionPercentage;
    }

    public function setMerchantCommissionPercentage(?float $merchantCommissionPercentage): self
    {
        $this->merchantCommissionPercentage = $merchantCommissionPercentage;

        return $this;
    }

    public function getMerchantCommissionFixed(): float
    {
        return $this->merchantCommissionFixed;
    }

    public function setMerchantCommissionFixed(?float $merchantCommissionFixed): self
    {
        $this->merchantCommissionFixed = $merchantCommissionFixed;

        return $this;
    }

    public function getUserCommissionFixed(): float
    {
        return $this->userCommissionFixed;
    }

    public function setUserCommissionFixed(?float $userCommissionFixed): self
    {
        $this->userCommissionFixed = $userCommissionFixed;

        return $this;
    }

    public function getUserCommissionPercentage(): float
    {
        return $this->userCommissionPercentage;
    }

    public function setUserCommissionPercentage(?float $userCommissionPercentage): self
    {
        $this->userCommissionPercentage = $userCommissionPercentage;

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

    public function getInstallment(): int
    {
        return $this->installment;
    }

    public function setInstallment(int $installment): self
    {
        $this->installment = $installment;

        return $this;
    }

    public function getPosId(): int
    {
        return $this->posId;
    }

    public function setPosId(int $posId): self
    {
        $this->posId = $posId;

        return $this;
    }

    public function getGetposCardProgram(): string
    {
        return $this->getposCardProgram;
    }

    public function setGetposCardProgram(string $getposCardProgram): self
    {
        $this->getposCardProgram = $getposCardProgram;

        return $this;
    }
}
