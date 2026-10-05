<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\CreateTransactionRefundRequest;
use Sipay\Resources\Mapper\TransactionRefundMapper;
use Sipay\SipayResource;

class TransactionRefund extends SipayResource
{
    private $orderNo;

    private $invoiceId;

    private $refNo;

    private $refNumber;

    public function create(CreateTransactionRefundRequest $request): self
    {
        $response = $this->request('post', '/api/refund', $request);

        return $this->createMapper(TransactionRefundMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
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

    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    public function getRefNo()
    {
        return $this->refNo;
    }

    public function setRefNo($refNo)
    {
        $this->refNo = $refNo;

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
}
