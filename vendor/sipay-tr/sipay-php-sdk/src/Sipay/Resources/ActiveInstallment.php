<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\RetrieveActiveInstallmentRequest;
use Sipay\Resources\Mapper\ActiveInstallmentMapper;
use Sipay\SipayResource;

class ActiveInstallment extends SipayResource
{
    /**
     * @var array<int>
     */
    private array $installments;

    public function retrieve(RetrieveActiveInstallmentRequest $request): ActiveInstallment
    {
        $response = $this->request('post', '/api/installments', $request);

        return $this->createMapper(ActiveInstallmentMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function getInstallments(): array
    {
        return $this->installments;
    }

    /**
     * @param array<int> $installments
     * @return $this
     */
    public function setInstallments(array $installments): self
    {
        $this->installments = $installments;

        return $this;
    }
}
