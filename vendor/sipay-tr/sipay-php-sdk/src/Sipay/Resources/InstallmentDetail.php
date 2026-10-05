<?php

declare(strict_types=1);

namespace Sipay\Resources;

class InstallmentDetail
{
    private $pos_id;

    private $campaign_id;

    private $allocation_id;

    private $installments_number;

    private $card_type;

    private $card_program;

    private $card_scheme;

    private $is_commercial;

    private $payable_amount;

    private $hash_key;

    private $amount_to_be_paid;

    private $currency_code;

    private $currency_id;

    private $title;

    private $maturity_period;

    private $payment_frequency;

    public function getPosId()
    {
        return $this->pos_id;
    }

    public function setPosId($pos_id): self
    {
        $this->pos_id = $pos_id;

        return $this;
    }

    public function getCampaignId()
    {
        return $this->campaign_id;
    }

    public function setCampaignId($campaign_id): self
    {
        $this->campaign_id = $campaign_id;

        return $this;
    }

    public function getAllocationId()
    {
        return $this->allocation_id;
    }

    public function setAllocationId($allocation_id): self
    {
        $this->allocation_id = $allocation_id;

        return $this;
    }

    public function getInstallmentsNumber()
    {
        return $this->installments_number;
    }

    public function setInstallmentsNumber($installments_number): self
    {
        $this->installments_number = $installments_number;

        return $this;
    }

    public function getCardType()
    {
        return $this->card_type;
    }

    public function setCardType($card_type): self
    {
        $this->card_type = $card_type;

        return $this;
    }

    public function getCardProgram()
    {
        return $this->card_program;
    }

    public function setCardProgram($card_program): self
    {
        $this->card_program = $card_program;

        return $this;
    }

    public function getCardScheme()
    {
        return $this->card_scheme;
    }

    public function setCardScheme($card_scheme): self
    {
        $this->card_scheme = $card_scheme;

        return $this;
    }

    public function getIsCommercial()
    {
        return $this->is_commercial;
    }

    public function setIsCommercial($is_commercial): self
    {
        $this->is_commercial = $is_commercial;

        return $this;
    }

    public function getPayableAmount()
    {
        return $this->payable_amount;
    }

    public function setPayableAmount($payable_amount): self
    {
        $this->payable_amount = $payable_amount;

        return $this;
    }

    public function getHashKey()
    {
        return $this->hash_key;
    }

    public function setHashKey($hash_key): self
    {
        $this->hash_key = $hash_key;

        return $this;
    }

    public function getAmountToBePaid()
    {
        return $this->amount_to_be_paid;
    }

    public function setAmountToBePaid($amount_to_be_paid): self
    {
        $this->amount_to_be_paid = $amount_to_be_paid;

        return $this;
    }

    public function getCurrencyCode()
    {
        return $this->currency_code;
    }

    public function setCurrencyCode($currency_code): self
    {
        $this->currency_code = $currency_code;

        return $this;
    }

    public function getCurrencyId()
    {
        return $this->currency_id;
    }

    public function setCurrencyId($currency_id): self
    {
        $this->currency_id = $currency_id;

        return $this;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getMaturityPeriod()
    {
        return $this->maturity_period;
    }

    public function setMaturityPeriod($maturity_period): self
    {
        $this->maturity_period = $maturity_period;

        return $this;
    }

    public function getPaymentFrequency()
    {
        return $this->payment_frequency;
    }

    public function setPaymentFrequency($payment_frequency): self
    {
        $this->payment_frequency = $payment_frequency;

        return $this;
    }
}
