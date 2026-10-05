<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\CreateNonSecurePaymentRequest;
use Sipay\Resources\Concerns\InteractsPaymentMethod;
use Sipay\Resources\Mapper\NonSecurePaymentMapper;
use Sipay\SipayResource;

class NonSecurePayment extends SipayResource
{
    use InteractsPaymentMethod;

    private $sipayStatus;

    private $orderNo;

    private $orderId;

    private $invoiceId;

    private $sipayPaymentMethod;

    private $creditCardNo;

    private $transactionType;

    private $paymentStatus;

    private $paymentMethod;

    private $authCode;

    private $merchantCommission;

    private $userCommission;

    private $merchantCommissionPercentage;

    private $merchantCommissionFixed;

    private $installment;

    private $amount;

    private $hashKey;

    public function create(CreateNonSecurePaymentRequest $request): self
    {
        $response = $this->request('post', "/api/paySmart2D", $request);

        return $this->createMapper(NonSecurePaymentMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function getSipayStatus()
    {
        return $this->sipayStatus;
    }

    public function setSipayStatus($sipayStatus)
    {
        $this->sipayStatus = $sipayStatus;

        return $this;
    }

    public function getOrderNo()
    {
        return $this->orderNo;
    }

    public function setOrderNo($orderNo)
    {
        $this->orderNo = $orderNo;

        return $this;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    public function getSipayPaymentMethod()
    {
        return $this->sipayPaymentMethod;
    }

    public function setSipayPaymentMethod($sipayPaymentMethod)
    {
        $this->sipayPaymentMethod = $sipayPaymentMethod;

        return $this;
    }

    public function getCreditCardNo()
    {
        return $this->creditCardNo;
    }

    public function setCreditCardNo($creditCardNo)
    {
        $this->creditCardNo = $creditCardNo;

        return $this;
    }

    public function getTransactionType()
    {
        return $this->transactionType;
    }

    public function setTransactionType($transactionType)
    {
        $this->transactionType = $transactionType;

        return $this;
    }

    public function getPaymentStatus()
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus($paymentStatus)
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getAuthCode()
    {
        return $this->authCode;
    }

    public function setAuthCode($authCode)
    {
        $this->authCode = $authCode;

        return $this;
    }

    public function getMerchantCommission()
    {
        return $this->merchantCommission;
    }

    public function setMerchantCommission($merchantCommission)
    {
        $this->merchantCommission = $merchantCommission;

        return $this;
    }

    public function getUserCommission()
    {
        return $this->userCommission;
    }

    public function setUserCommission($userCommission)
    {
        $this->userCommission = $userCommission;

        return $this;
    }

    public function getMerchantCommissionPercentage()
    {
        return $this->merchantCommissionPercentage;
    }

    public function setMerchantCommissionPercentage($merchantCommissionPercentage)
    {
        $this->merchantCommissionPercentage = $merchantCommissionPercentage;

        return $this;
    }

    public function getMerchantCommissionFixed()
    {
        return $this->merchantCommissionFixed;
    }

    public function setMerchantCommissionFixed($merchantCommissionFixed)
    {
        $this->merchantCommissionFixed = $merchantCommissionFixed;

        return $this;
    }

    public function getInstallment()
    {
        return $this->installment;
    }

    public function setInstallment($installment)
    {
        $this->installment = $installment;

        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    public function getHashKey()
    {
        return $this->hashKey;
    }

    public function setHashKey($hashKey)
    {
        $this->hashKey = $hashKey;

        return $this;
    }
}
