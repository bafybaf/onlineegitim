<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\CheckTransactionStatusRequest;
use Sipay\Resources\Mapper\TransactionStatusMapper;
use Sipay\SipayResource;

class TransactionStatus extends SipayResource
{
    private $transactionStatus;

    private $orderId;

    private $transactionId;

    private $message;

    private $invoiceId;

    private $totalRefundedAmount;

    private $productPrice;

    private $transactionAmount;

    private $refNumber;

    private $transactionType;

    private $ccNo;

    private $merchantCommission;

    private $userCommission;

    private $settlementDate;

    private $installment;

    private $cardType;

    private $recurringId;

    private $recurringPlanCode;

    private $nextActionDate;

    private $recurringStatus;

    public function retrieve(CheckTransactionStatusRequest $request): self
    {
        $response = $this->request('post', '/api/checkstatus', $request);

        return $this->createMapper(TransactionStatusMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function getTransactionStatus()
    {
        return $this->transactionStatus;
    }

    public function setTransactionStatus($transactionStatus)
    {
        $this->transactionStatus = $transactionStatus;

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

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;

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

    public function getTotalRefundedAmount()
    {
        return $this->totalRefundedAmount;
    }

    public function setTotalRefundedAmount($totalRefundedAmount)
    {
        $this->totalRefundedAmount = $totalRefundedAmount;

        return $this;
    }

    public function getProductPrice()
    {
        return $this->productPrice;
    }

    public function setProductPrice($productPrice)
    {
        $this->productPrice = $productPrice;

        return $this;
    }

    public function getTransactionAmount()
    {
        return $this->transactionAmount;
    }

    public function setTransactionAmount($transactionAmount)
    {
        $this->transactionAmount = $transactionAmount;

        return $this;
    }

    public function getRefNumber()
    {
        return $this->refNumber;
    }

    public function setRefNumber($refNumber)
    {
        $this->refNumber = $refNumber;

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

    public function getCcNo()
    {
        return $this->ccNo;
    }

    public function setCcNo($ccNo)
    {
        $this->ccNo = $ccNo;

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

    public function getSettlementDate()
    {
        return $this->settlementDate;
    }

    public function setSettlementDate($settlementDate)
    {
        $this->settlementDate = $settlementDate;

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

    public function getCardType()
    {
        return $this->cardType;
    }

    public function setCardType($cardType)
    {
        $this->cardType = $cardType;

        return $this;
    }

    public function getRecurringId()
    {
        return $this->recurringId;
    }

    public function setRecurringId($recurringId)
    {
        $this->recurringId = $recurringId;

        return $this;
    }

    public function getRecurringPlanCode()
    {
        return $this->recurringPlanCode;
    }

    public function setRecurringPlanCode($recurringPlanCode)
    {
        $this->recurringPlanCode = $recurringPlanCode;

        return $this;
    }

    public function getNextActionDate()
    {
        return $this->nextActionDate;
    }

    public function setNextActionDate($nextActionDate)
    {
        $this->nextActionDate = $nextActionDate;

        return $this;
    }

    public function getRecurringStatus()
    {
        return $this->recurringStatus;
    }

    public function setRecurringStatus($recurringStatus)
    {
        $this->recurringStatus = $recurringStatus;

        return $this;
    }
}
