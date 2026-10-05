<?php

declare(strict_types=1);

namespace Sipay\Requests\Concerns;

use Sipay\Utils\HashKey;

trait InteractsHashKey
{
    public function getHashKey(): string
    {
        return HashKey::generateHashKey($this->generateHashKeyParts(), $this->getApiSecret());
    }
}
