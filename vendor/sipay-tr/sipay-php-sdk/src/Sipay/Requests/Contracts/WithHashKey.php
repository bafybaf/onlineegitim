<?php

declare(strict_types=1);

namespace Sipay\Requests\Contracts;

interface WithHashKey
{
    public function generateHashKeyParts(): array;
}
