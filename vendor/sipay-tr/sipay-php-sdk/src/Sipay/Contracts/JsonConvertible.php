<?php

declare(strict_types=1);

namespace Sipay\Contracts;

interface JsonConvertible
{
    public function getJsonObject();

    public function toJsonString();
}
