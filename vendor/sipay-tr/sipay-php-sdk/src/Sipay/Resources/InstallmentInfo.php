<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\RetrieveInstallmentInfoRequest;
use Sipay\Resources\Mapper\InstallmentInfoMapper;
use Sipay\SipayResource;

class InstallmentInfo extends SipayResource
{
    private $installmentDetails;

    public function retrieve(RetrieveInstallmentInfoRequest $request): self
    {
        $curlResponse = $this->request('post', '/api/getpos', $request);

        return $this->createMapper(InstallmentInfoMapper::class, $curlResponse->getBody(), new self())
            ->mapJsonBody();
    }

    /**
     * @return array<InstallmentDetail>
     */
    public function getInstallmentDetails(): array
    {
        return $this->installmentDetails;
    }

    /**
     * @param mixed $installmentDetails
     */
    public function setInstallmentDetails(array $installmentDetails): self
    {
        $this->installmentDetails = $installmentDetails;

        return $this;
    }
}
