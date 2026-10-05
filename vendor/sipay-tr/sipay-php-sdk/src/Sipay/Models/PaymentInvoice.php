<?php

declare(strict_types=1);

namespace Sipay\Models;

use Sipay\Exceptions\InvalidArgumentException;
use Sipay\JsonBuilder;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;
use Sipay\RequestStringBuilder;

abstract class PaymentInvoice extends BasePaymentInvoice implements WithHashKey
{
    use InteractsHashKey;

    protected $currencyCode;

    protected $ccHolderName;

    protected $ccNo;

    protected $expiryMonth;

    protected $expiryYear;

    protected $cvv;

    protected $installmentsNumber;

    protected $name;

    protected $surname;

    protected $cardToken;

    protected $customerNumber;

    protected $customerEmail;

    protected $customerPhone;

    protected $customerName;

    /**
     * IP address of the card holder (the end user paying), sent as "ip".
     */
    protected $ip;

    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode($currencyCode)
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function getCcHolderName()
    {
        return $this->ccHolderName;
    }

    public function getCcNo()
    {
        return $this->ccNo;
    }

    public function getExpiryMonth()
    {
        return $this->expiryMonth;
    }

    public function getExpiryYear()
    {
        return $this->expiryYear;
    }

    public function getCvv()
    {
        return $this->cvv;
    }

    public function getInstallmentsNumber()
    {
        return $this->installmentsNumber;
    }

    public function setInstallmentsNumber($installmentsNumber)
    {
        $this->installmentsNumber = $installmentsNumber;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getSurname()
    {
        return $this->surname;
    }

    public function setSurname($surname)
    {
        $this->surname = $surname;

        return $this;
    }

    public function getCardToken()
    {
        return $this->cardToken;
    }

    public function getCustomerNumber()
    {
        return $this->customerNumber;
    }

    public function getCustomerEmail()
    {
        return $this->customerEmail;
    }

    public function getCustomerPhone()
    {
        return $this->customerPhone;
    }

    public function getCustomerName()
    {
        return $this->customerName;
    }

    public function getIp()
    {
        return $this->ip;
    }

    /**
     * Accepts what the gateway accepts: any valid IPv4 or IPv6 address. IPv4-mapped IPv6
     * ("::ffff:203.0.113.7") is unwrapped to plain IPv4. Pass null to clear the value.
     *
     * @throws InvalidArgumentException when the value is not a single valid IP address
     */
    public function setIp($ip)
    {
        if ($ip !== null) {
            if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                // The value is deliberately not echoed: an IP address is personal data and
                // exception messages end up in logs.
                throw new InvalidArgumentException('Card holder IP address must be a valid IPv4 or IPv6 address.');
            }

            $binary = inet_pton($ip);

            if (strlen($binary) === 16 && substr($binary, 0, 12) === str_repeat("\x00", 10) . "\xff\xff") {
                $ip = inet_ntop(substr($binary, 12));
            }
        }

        $this->ip = $ip;

        return $this;
    }

    public function generateHashKeyParts(): array
    {
        return [
            $this->getTotal(),
            $this->getInstallmentsNumber(),
            $this->getCurrencyCode(),
            $this->getMerchantKey(),
            $this->getInvoiceId(),
        ];
    }

    protected function getJsonBuilder(bool $encodeItems = false): JsonBuilder
    {
        $builder = JsonBuilder::create()
            ->add("merchant_key", $this->getMerchantKey())
            ->add("currency_code", $this->getCurrencyCode());

        if (!$this->getCardToken()) {
            $builder->add("cc_holder_name", $this->getCcHolderName())
                ->add("cc_no", $this->getCcNo())
                ->add("expiry_month", $this->getExpiryMonth())
                ->add("expiry_year", $this->getExpiryYear())
                ->add("cvv", $this->getCvv());
        } else {
            $builder->add("card_token", $this->getCardToken())
                ->add("customer_number", $this->getCustomerNumber())
                ->add("customer_email", $this->getCustomerEmail())
                ->add("customer_phone", $this->getCustomerPhone())
                ->add("customer_name", $this->getCustomerName());
        }

        $items = $this->getItems();

        if ($encodeItems) {
            $builder->add("items", json_encode($items));
        } else {
            $builder->addArray("items", $items);
        }

        $builder->add("installments_number", $this->getInstallmentsNumber())
            ->add("name", $this->getName())
            ->add("surname", $this->getSurname())
            ->add("invoice_id", $this->getInvoiceId())
            ->add("total", $this->getTotal())
            ->add("cancel_url", $this->getCancelUrl())
            ->add("return_url", $this->getReturnUrl())
            ->add("bill_address1", $this->getBillAddress1())
            ->add("bill_address2", $this->getBillAddress2())
            ->add("bill_city", $this->getBillCity())
            ->add("bill_postcode", $this->getBillPostcode())
            ->add("bill_state", $this->getBillState())
            ->add("bill_country", $this->getBillCountry())
            ->add("bill_phone", $this->getBillPhone())
            ->add("bill_email", $this->getBillEmail())
            ->add("ip", $this->getIp())
            ->add("discount", $this->getDiscount())
            ->add("coupon", $this->getCoupon())
            ->add("transaction_type", $this->getTransactionType())
            ->add("order_type", $this->getOrderType())
            ->add("recurring_payment_number", $this->getRecurringPaymentNumber())
            ->add("recurring_payment_cycle", $this->getRecurringPaymentCycle())
            ->add("recurring_payment_interval", $this->getRecurringPaymentInterval())
            ->add("recurring_web_hook_key", $this->getRecurringWebHookKey())
            ->add("sale_web_hook_key", $this->getSaleWebHookKey())
            ->add("hash_key", $this->getHashKey());

        return $builder;
    }

    public function getJsonObject(bool $encodeItems = false)
    {
        return $this->getJsonBuilder($encodeItems)->getObject();
    }

    public function toPKIRequestString(bool $encodeItems = false)
    {
        $builder = RequestStringBuilder::create()
            ->append("merchant_key", $this->getMerchantKey())
            ->append("currency_code", $this->getCurrencyCode());

        if (empty($this->getCardToken())) {
            $builder->append("cc_holder_name", $this->getCcHolderName())
                ->append("cc_no", $this->getCcNo())
                ->append("expiry_month", $this->getExpiryMonth())
                ->append("expiry_year", $this->getExpiryYear())
                ->append("cvv", $this->getCvv());
        } else {
            $builder->append("card_token", $this->getCardToken())
                ->append("customer_number", $this->getCustomerNumber())
                ->append("customer_email", $this->getCustomerEmail())
                ->append("customer_phone", $this->getCustomerPhone())
                ->append("customer_name", $this->getCustomerName());
        }

        $items = $this->getItems();

        if ($encodeItems) {
            $builder->append("items", json_encode($items));
        } else {
            $builder->appendArray("items", $items);
        }

        $builder->append("installments_number", $this->getInstallmentsNumber())
            ->append("name", $this->getName())
            ->append("surname", $this->getSurname())
            ->append("invoice_id", $this->getInvoiceId())
            ->append("total", $this->getTotal())
            ->append("cancel_url", $this->getCancelUrl())
            ->append("return_url", $this->getReturnUrl())
            ->append("bill_address1", $this->getBillAddress1())
            ->append("bill_address2", $this->getBillAddress2())
            ->append("bill_city", $this->getBillCity())
            ->append("bill_postcode", $this->getBillPostcode())
            ->append("bill_state", $this->getBillState())
            ->append("bill_country", $this->getBillCountry())
            ->append("bill_phone", $this->getBillPhone())
            ->append("bill_email", $this->getBillEmail())
            ->append("ip", $this->getIp())
            ->append("discount", $this->getDiscount())
            ->append("coupon", $this->getCoupon())
            ->append("transaction_type", $this->getTransactionType())
            ->append("order_type", $this->getOrderType())
            ->append("recurring_payment_number", $this->getRecurringPaymentNumber())
            ->append("recurring_payment_cycle", $this->getRecurringPaymentCycle())
            ->append("recurring_payment_interval", $this->getRecurringPaymentInterval())
            ->append("recurring_web_hook_key", $this->getRecurringWebHookKey())
            ->append("sale_web_hook_key", $this->getSaleWebHookKey())
            ->append("hash_key", $this->getHashKey());

        return $builder->getRequestString();
    }
}
