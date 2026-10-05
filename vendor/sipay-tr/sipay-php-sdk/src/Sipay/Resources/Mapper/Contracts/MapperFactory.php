<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper\Contracts;

use Sipay\Resources\Mapper\SipayResourceMapper;
use Sipay\SipayResource;

interface MapperFactory
{
    public function createMapper(string $type, $rawResult, SipayResource $resource): SipayResourceMapper;
}
