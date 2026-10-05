<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Mapper\Contracts\JsonMapper;
use Sipay\Resources\PaymentLink;

/**
 * @property PaymentLink $resource
 */
class PaymentLinkMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): PaymentLink
    {
        if (property_exists($this->jsonBody, 'success_message')) {
            $this->resource->setSuccessMessage($this->jsonBody->success_message);
        }
        if (property_exists($this->jsonBody, 'link')) {
            $this->resource->setLink($this->jsonBody->link);
        }
        if (property_exists($this->jsonBody, 'order_id')) {
            $this->resource->setOrderId($this->jsonBody->order_id);
        }

        return $this->resource;
    }
}
