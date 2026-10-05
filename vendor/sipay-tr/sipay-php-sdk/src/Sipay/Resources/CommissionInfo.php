<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\RetrieveCommissionRequest;
use Sipay\Resources\Mapper\CommissionMapper;
use Sipay\SipayResource;

class CommissionInfo extends SipayResource
{
    /**
     * @var array<int, array<CommissionDetail>>
     */
    private array $commissionDetails = [];

    public function retrieve(RetrieveCommissionRequest $request): self
    {
        $response = $this->request('post', '/api/commissions', $request);

        return $this->createMapper(CommissionMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function getCommissionDetails(): array
    {
        return $this->commissionDetails;
    }

    /**
     * @param array<int, array<CommissionDetail>> $commissionDetails
     * @return $this
     */
    public function setCommissionDetails(array $commissionDetails): CommissionInfo
    {
        $this->commissionDetails = $commissionDetails;

        return $this;
    }
}
