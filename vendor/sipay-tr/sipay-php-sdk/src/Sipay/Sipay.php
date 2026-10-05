<?php

declare(strict_types=1);

namespace Sipay;

use Sipay\Exceptions\InvalidArgumentException;
use Sipay\Resources\ActiveInstallment;
use Sipay\Resources\Card;
use Sipay\Resources\CardList;
use Sipay\Resources\CommissionInfo;
use Sipay\Resources\CompletePayment;
use Sipay\Resources\ConfirmPayment;
use Sipay\Resources\InstallmentInfo;
use Sipay\Resources\NonSecurePayment;
use Sipay\Resources\PaymentLink;
use Sipay\Resources\SavedCardPayment;
use Sipay\Resources\SecurePayment;
use Sipay\Resources\Token;
use Sipay\Resources\TransactionRefund;
use Sipay\Resources\TransactionStatus;

class Sipay
{
    private SipayOptions $options;

    public function __construct($options)
    {
        $this->setOptions($options);
    }

    public function setOptions($options): self
    {
        if (is_array($options)) {
            $options = SipayOptions::create($options);
        }

        if (!$options instanceof SipayOptions) {
            throw new InvalidArgumentException(sprintf(
                'Argument $options must be either instance of %s or an array, %s given',
                'Sipay\SipayOptions',
                gettype($options)
            ));
        }

        $this->options = $options;

        return $this;
    }

    public function getOptions(): SipayOptions
    {
        return $this->options;
    }

    public function cardResource(): Card
    {
        return new Card($this->options);
    }

    public function cardListResource(): CardList
    {
        return new CardList($this->options);
    }

    public function createRequest(string $requestClass): RequestPayload
    {
        return new $requestClass($this->options);
    }

    public function createModel(string $modelClass): Model
    {
        return new $modelClass($this->options);
    }

    public function nonSecurePaymentResource(): NonSecurePayment
    {
        return new NonSecurePayment($this->options);
    }

    public function securePaymentResource(): SecurePayment
    {
        return new SecurePayment($this->options);
    }

    public function installmentInfoResource(): InstallmentInfo
    {
        return new InstallmentInfo($this->options);
    }

    public function activeInstallmentResource(): ActiveInstallment
    {
        return new ActiveInstallment($this->options);
    }

    public function commissionInfoResource(): CommissionInfo
    {
        return new CommissionInfo($this->options);
    }

    public function completePaymentResource(): CompletePayment
    {
        return new CompletePayment($this->options);
    }

    public function confirmPaymentResource(): ConfirmPayment
    {
        return new ConfirmPayment($this->options);
    }

    public function paymentLinkResource(): PaymentLink
    {
        return new PaymentLink($this->options);
    }

    public function savedCardPaymentResource(): SavedCardPayment
    {
        return new SavedCardPayment($this->options);
    }

    public function tokenResource(): Token
    {
        return new Token($this->options);
    }

    public function transactionRefundResource(): TransactionRefund
    {
        return new TransactionRefund($this->options);
    }

    public function transactionStatusResource(): TransactionStatus
    {
        return new TransactionStatus($this->options);
    }
}
