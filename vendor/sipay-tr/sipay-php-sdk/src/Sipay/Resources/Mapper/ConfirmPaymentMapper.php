<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\ConfirmPayment;
use Sipay\Resources\Mapper\Contracts\JsonMapper;

/**
 * @property ConfirmPayment $resource
 */
class ConfirmPaymentMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): ConfirmPayment
    {
        if (isset($this->jsonBody->transaction_status)) {
            $this->resource->setTransactionStatus($this->jsonBody->transaction_status);
        }
        if (isset($this->jsonBody->order_id)) {
            $this->resource->setOrderId($this->jsonBody->order_id);
        }
        if (isset($this->jsonBody->invoice_id)) {
            $this->resource->setInvoiceId($this->jsonBody->invoice_id);
        }

        return $this->resource;
    }
}
