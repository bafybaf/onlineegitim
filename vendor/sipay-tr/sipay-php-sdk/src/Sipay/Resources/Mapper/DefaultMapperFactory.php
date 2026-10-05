<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Mapper\Contracts\MapperFactory;
use Sipay\SipayResource;

class DefaultMapperFactory implements MapperFactory
{
    public function createMapper(string $type, $rawResult, SipayResource $resource): SipayResourceMapper
    {
        $mapperClass = "Sipay\\Resources\\Mapper\\{$type}Mapper";

        return new $mapperClass($rawResult, $resource);
    }
}
