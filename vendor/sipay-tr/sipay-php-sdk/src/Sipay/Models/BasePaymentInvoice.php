<?php

declare(strict_types=1);

namespace Sipay\Models;

use Sipay\Model;

abstract class BasePaymentInvoice extends Model
{
    protected $invoiceId;

    protected $total;

    protected $items;

    protected $cancelUrl;

    protected $returnUrl;

    protected $billAddress1;

    protected $billAddress2;

    protected $billCity;

    protected $billPostcode;

    protected $billState;

    protected $billCountry;

    protected $billPhone;

    protected $billEmail;

    protected $discount;

    protected $coupon;

    protected $transactionType;

    protected $orderType;

    protected $recurringPaymentNumber;

    protected $recurringPaymentCycle;

    protected $recurringPaymentInterval;

    protected $recurringWebHookKey;

    protected $saleWebHookKey;

    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items)
    {
        $this->items = $items;

        return $this;
    }

    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    public function setCancelUrl($cancelUrl)
    {
        $this->cancelUrl = $cancelUrl;

        return $this;
    }

    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    public function getBillAddress1()
    {
        return $this->billAddress1;
    }

    public function setBillAddress1($billAddress1)
    {
        $this->billAddress1 = $billAddress1;

        return $this;
    }

    public function getBillAddress2()
    {
        return $this->billAddress2;
    }

    public function setBillAddress2($billAddress2)
    {
        $this->billAddress2 = $billAddress2;

        return $this;
    }

    public function getBillCity()
    {
        return $this->billCity;
    }

    public function setBillCity($billCity)
    {
        $this->billCity = $billCity;

        return $this;
    }

    public function getBillPostcode()
    {
        return $this->billPostcode;
    }

    public function setBillPostcode($billPostcode)
    {
        $this->billPostcode = $billPostcode;

        return $this;
    }

    public function getBillState()
    {
        return $this->billState;
    }

    public function setBillState($billState)
    {
        $this->billState = $billState;

        return $this;
    }

    public function getBillCountry()
    {
        return $this->billCountry;
    }

    public function setBillCountry($billCountry)
    {
        $this->billCountry = $billCountry;

        return $this;
    }

    public function getBillPhone()
    {
        return $this->billPhone;
    }

    public function setBillPhone($billPhone)
    {
        $this->billPhone = $billPhone;

        return $this;
    }

    public function getBillEmail()
    {
        return $this->billEmail;
    }

    public function setBillEmail($billEmail)
    {
        $this->billEmail = $billEmail;

        return $this;
    }

    public function getDiscount()
    {
        return $this->discount;
    }

    public function setDiscount($discount)
    {
        $this->discount = $discount;

        return $this;
    }

    public function getCoupon()
    {
        return $this->coupon;
    }

    public function setCoupon($coupon)
    {
        $this->coupon = $coupon;

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

    public function getOrderType()
    {
        return $this->orderType;
    }

    public function setOrderType($orderType)
    {
        $this->orderType = $orderType;

        return $this;
    }

    public function getRecurringPaymentNumber()
    {
        return $this->recurringPaymentNumber;
    }

    public function setRecurringPaymentNumber($recurringPaymentNumber)
    {
        $this->recurringPaymentNumber = $recurringPaymentNumber;

        return $this;
    }

    public function getRecurringPaymentCycle()
    {
        return $this->recurringPaymentCycle;
    }

    public function setRecurringPaymentCycle($recurringPaymentCycle)
    {
        $this->recurringPaymentCycle = $recurringPaymentCycle;

        return $this;
    }

    public function getRecurringPaymentInterval()
    {
        return $this->recurringPaymentInterval;
    }

    public function setRecurringPaymentInterval($recurringPaymentInterval)
    {
        $this->recurringPaymentInterval = $recurringPaymentInterval;

        return $this;
    }

    public function getRecurringWebHookKey()
    {
        return $this->recurringWebHookKey;
    }

    public function setRecurringWebHookKey($recurringWebHookKey)
    {
        $this->recurringWebHookKey = $recurringWebHookKey;

        return $this;
    }

    public function getSaleWebHookKey()
    {
        return $this->saleWebHookKey;
    }

    public function setSaleWebHookKey($saleWebHookKey)
    {
        $this->saleWebHookKey = $saleWebHookKey;

        return $this;
    }
}
