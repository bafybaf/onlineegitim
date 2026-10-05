<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\RetrieveTokenRequest;
use Sipay\Resources\Mapper\TokenMapper;
use Sipay\SipayResource;

class Token extends SipayResource
{
    private $token;

    private $is3D;

    private $expiresAt;

    public function retrieve(RetrieveTokenRequest $request): Token
    {
        $response = $this->request('post', "/api/token", $request, [], false);

        return $this->createMapper(TokenMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIs3D(): ?int
    {
        return $this->is3D;
    }

    /**
     * @param mixed $is3D
     */
    public function setIs3D(?int $is3D): self
    {
        $this->is3D = $is3D;

        return $this;
    }

    public function getExpiresAt()
    {
        return $this->expiresAt; // TODO: datetime çevir. Format: "2025-01-14 22:53:53"
    }

    public function setExpiresAt($expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }
}
