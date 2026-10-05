<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\CreateSavedCardPaymentRequest;
use Sipay\Resources\Mapper\SavedCardPaymentMapper;
use Sipay\SipayResource;

class SavedCardPayment extends SipayResource
{
    private $htmlForm;

    public function create(CreateSavedCardPaymentRequest $request): self
    {
        $response = $this->request('post', '/api/payByCardToken', $request);

        return $this->createMapper(SavedCardPaymentMapper::class, $response->getBody(), new self())
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
