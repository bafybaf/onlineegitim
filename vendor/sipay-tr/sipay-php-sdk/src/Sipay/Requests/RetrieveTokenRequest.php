<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\RequestStringBuilder;

class RetrieveTokenRequest extends RequestPayload
{
    private $appId;

    private $appSecret;

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): self
    {
        $this->appId = $appId;

        return $this;
    }

    public function getAppSecret(): ?string
    {
        return $this->appSecret;
    }

    public function setAppSecret(string $appSecret): self
    {
        $this->appSecret = $appSecret;

        return $this;
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("app_id", $this->getAppId())
            ->add("app_secret", $this->getAppSecret())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("app_id", $this->getAppId())
            ->append("app_secret", $this->getAppSecret())
            ->getRequestString();
    }
}
