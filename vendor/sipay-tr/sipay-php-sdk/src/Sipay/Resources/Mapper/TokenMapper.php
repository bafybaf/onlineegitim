<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Mapper\Contracts\JsonMapper;
use Sipay\Resources\Token;

/**
 * @property Token $resource
 */
class TokenMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): Token
    {
        if (isset($this->jsonBody->data)) {
            $data = $this->jsonBody->data;

            if (isset($data->token)) {
                $this->resource->setToken($data->token);
            }
            if (isset($data->is_3d)) {
                $this->resource->setIs3D($data->is_3d);
            }
            if (isset($data->expires_at)) {
                $this->resource->setExpiresAt($data->expires_at);
            }
        }

        return $this->resource;
    }
}
