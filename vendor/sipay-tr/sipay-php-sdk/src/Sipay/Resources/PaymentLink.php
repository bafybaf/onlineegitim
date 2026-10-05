<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\CreatePaymentLinkRequest;
use Sipay\Resources\Mapper\PaymentLinkMapper;
use Sipay\SipayResource;

class PaymentLink extends SipayResource
{
    private $successMessage;

    private $link;

    private $orderId;

    public function generate(CreatePaymentLinkRequest $request): self
    {
        $response = $this->request('post', '/purchase/link', $request);

        return $this->createMapper(PaymentLinkMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function getSuccessMessage()
    {
        return $this->successMessage;
    }

    public function setSuccessMessage($successMessage)
    {
        $this->successMessage = $successMessage;

        return $this;
    }

    public function getLink()
    {
        return $this->link;
    }

    public function setLink($link)
    {
        $this->link = $link;

        return $this;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }
}
