<?php

declare(strict_types=1);

namespace Sipay\Contracts;

interface RequestStringConvertible
{
    public function toPKIRequestString();
}
