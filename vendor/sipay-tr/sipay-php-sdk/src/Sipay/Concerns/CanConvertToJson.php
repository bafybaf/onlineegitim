<?php

declare(strict_types=1);

namespace Sipay\Concerns;

use Sipay\JsonBuilder;

trait CanConvertToJson
{
    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->getObject();
    }

    public function toJsonString()
    {
        return JsonBuilder::jsonEncode($this->getJsonObject());
    }
}
