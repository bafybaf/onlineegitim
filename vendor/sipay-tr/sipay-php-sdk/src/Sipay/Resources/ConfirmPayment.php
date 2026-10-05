<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\ConfirmPaymentRequest;
use Sipay\Resources\Mapper\ConfirmPaymentMapper;
use Sipay\SipayResource;

class ConfirmPayment extends SipayResource
{
    private $transactionStatus;

    private $orderId;

    private $invoiceId;

    public function create(ConfirmPaymentRequest $request): self
    {
        $response = $this->request('post', '/api/confirmPayment', $request);

        return $this->createMapper(ConfirmPaymentMapper::class, $response->getBody(), new self())
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

    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }
}
