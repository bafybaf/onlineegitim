<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\ActiveInstallment;
use Sipay\Resources\Mapper\Contracts\JsonMapper;

/**
 * @property ActiveInstallment $resource
 */
class ActiveInstallmentMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): ActiveInstallment
    {
        if (property_exists($this->jsonBody, 'installments')) {
            $this->resource->setInstallments($this->jsonBody->installments);
        }

        return $this->resource;
    }
}
