<?php

declare(strict_types=1);

namespace Sipay\Models;

/*
 * 3D Ödeme
 */
class SecurePaymentInvoice extends PaymentInvoice
{
    protected $paymentCompletedBy;

    public function getPaymentCompletedBy()
    {
        return $this->paymentCompletedBy;
    }

    public function setPaymentCompletedBy($paymentCompletedBy): self
    {
        $this->paymentCompletedBy = $paymentCompletedBy;

        return $this;
    }

    public function setNewCard($ccHolderName, $ccNo, $expiryMonth, $expiryYear, $cvv): self
    {
        $this->ccHolderName = $ccHolderName;
        $this->ccNo = $ccNo;
        $this->expiryMonth = $expiryMonth;
        $this->expiryYear = $expiryYear;
        $this->cvv = $cvv;

        return $this;
    }

    protected function getJsonBuilder(bool $encodeItems = false): \Sipay\JsonBuilder
    {
        $builder = parent::getJsonBuilder(true);

        $builder->add('payment_completed_by', $this->getPaymentCompletedBy());

        return $builder;
    }

    public function getJsonObject(bool $encodeItems = false)
    {
        return parent::getJsonObject(true);
    }
}
