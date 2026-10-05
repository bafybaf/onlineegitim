<?php

declare(strict_types=1);

namespace Sipay\Models;

use Sipay\JsonBuilder;
use Sipay\Model;
use Sipay\RequestStringBuilder;

class PaymentLinkInvoiceItem extends Model
{
    private $name;

    private $price;

    private $quantity;

    private $description;

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("name", $this->getName())
            ->add("price", $this->getPrice())
            ->add("quantity", $this->getQuantity())
            ->add("description", $this->getDescription())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("name", $this->getName())
            ->append("price", $this->getPrice())
            ->append("quantity", $this->getQuantity())
            ->append("description", $this->getDescription())
            ->getRequestString();
    }
}
