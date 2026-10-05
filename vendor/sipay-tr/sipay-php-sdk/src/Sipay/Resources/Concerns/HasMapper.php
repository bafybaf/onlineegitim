<?php

declare(strict_types=1);

namespace Sipay\Resources\Concerns;

trait HasMapper
{
    protected function createMapper(string $mapperClass, $rawResult, $resource)
    {
        return new $mapperClass($rawResult, $resource);
    }
}
