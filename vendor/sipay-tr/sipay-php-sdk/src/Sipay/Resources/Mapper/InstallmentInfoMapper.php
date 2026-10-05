<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\InstallmentDetail;
use Sipay\Resources\InstallmentInfo;
use Sipay\Resources\Mapper\Contracts\JsonMapper;

/**
 * @property InstallmentInfo $resource
 */
class InstallmentInfoMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): InstallmentInfo
    {
        if (property_exists($this->jsonBody, 'data')) {
            $this->resource->setInstallmentDetails($this->mapInstallmentDetails($this->jsonBody->data));
        }

        return $this->resource;
    }

    private function mapInstallmentDetails(array $installmentDetails): array
    {
        $details = [];

        foreach ($installmentDetails as $index => $data) {
            $resource = new InstallmentDetail();

            if (property_exists($data, 'pos_id')) {
                $resource->setPosId($data->pos_id);
            }
            if (property_exists($data, 'campaign_id')) {
                $resource->setCampaignId($data->campaign_id);
            }
            if (property_exists($data, 'allocation_id')) {
                $resource->setAllocationId($data->allocation_id);
            }
            if (property_exists($data, 'installments_number')) {
                $resource->setInstallmentsNumber($data->installments_number);
            }
            if (property_exists($data, 'card_type')) {
                $resource->setCardType($data->card_type);
            }
            if (property_exists($data, 'card_program')) {
                $resource->setCardProgram($data->card_program);
            }
            if (property_exists($data, 'card_scheme')) {
                $resource->setCardScheme($data->card_scheme);
            }
            if (property_exists($data, 'is_commercial')) {
                $resource->setIsCommercial($data->is_commercial);
            }
            if (property_exists($data, 'payable_amount')) {
                $resource->setPayableAmount($data->payable_amount);
            }
            if (property_exists($data, 'hash_key')) {
                $resource->setHashKey($data->hash_key);
            }
            if (property_exists($data, 'amount_to_be_paid')) {
                $resource->setAmountToBePaid($data->amount_to_be_paid);
            }
            if (property_exists($data, 'currency_code')) {
                $resource->setCurrencyCode($data->currency_code);
            }
            if (property_exists($data, 'currency_id')) {
                $resource->setCurrencyId($data->currency_id);
            }
            if (property_exists($data, 'title')) {
                $resource->setTitle($data->title);
            }
            if (property_exists($data, 'maturity_period')) {
                $resource->setMaturityPeriod($data->maturity_period);
            }
            if (property_exists($data, 'payment_frequency')) {
                $resource->setPaymentFrequency($data->payment_frequency);
            }

            $details[$index] = $resource;
        }

        return $details;
    }
}
