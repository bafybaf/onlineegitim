<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\CreateSecurePaymentRequest;
use Sipay\Resources\Mapper\SecurePaymentMapper;
use Sipay\SipayResource;

class SecurePayment extends SipayResource
{
    private $htmlForm;

    public function create(CreateSecurePaymentRequest $request): self
    {
        $response = $this->request('post', '/api/paySmart3D', $request);

        return $this->createMapper(SecurePaymentMapper::class, $response->getBody(), new self())
            ->mapHtmlBody();
    }

    public function getHtmlForm()
    {
        return $this->htmlForm;
    }

    public function setHtmlForm($htmlForm)
    {
        $this->htmlForm = $htmlForm;

        return $this;
    }
}
