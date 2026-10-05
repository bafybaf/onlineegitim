<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\CommissionDetail;
use Sipay\Resources\CommissionInfo;
use Sipay\Resources\Mapper\Contracts\JsonMapper;

/**
 * @property CommissionInfo $resource
 */
class CommissionMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): CommissionInfo
    {
        $data = $this->jsonBody->data;

        if (isset($data)) {
            $commissionDetails = $this->mapCommissionDetails((array) $data);
            $this->resource->setCommissionDetails($commissionDetails);
        }

        return $this->resource;
    }

    public function mapCommissionDetails(array $commissionDetails): array
    {
        $details = [];

        foreach ($commissionDetails as $installment => $commissionDetailsByInstallment) {
            foreach ($commissionDetailsByInstallment as $index => $data) {
                $resource = new CommissionDetail();

                if (isset($data->title)) {
                    $resource->setTitle($data->title);
                }
                if (isset($data->card_program)) {
                    $cardProgram = $data->card_program;
                    if (is_string($cardProgram) && trim($cardProgram) === '') {
                        $cardProgram = null;
                    }

                    $resource->setCardProgram($cardProgram);
                }
                if (isset($data->merchant_commission_percentage)) {
                    $merchantCommissionPercentage = $data->merchant_commission_percentage;

                    if ($this->isCartProgramDisabledForInstallment($merchantCommissionPercentage)) {
                        $merchantCommissionPercentage = null;
                    }

                    if (is_numeric($merchantCommissionPercentage)) {
                        $merchantCommissionPercentage = (float) $merchantCommissionPercentage;
                    }

                    $resource->setMerchantCommissionPercentage($merchantCommissionPercentage);
                }
                if (isset($data->merchant_commission_fixed)) {
                    $merchantCommissionFixed = $data->merchant_commission_fixed;

                    if ($this->isCartProgramDisabledForInstallment($merchantCommissionFixed)) {
                        $merchantCommissionFixed = null;
                    }

                    if (is_numeric($merchantCommissionFixed)) {
                        $merchantCommissionFixed = (float) $merchantCommissionFixed;
                    }

                    $resource->setMerchantCommissionFixed($merchantCommissionFixed);
                }
                if (isset($data->user_commission_fixed)) {
                    $userCommissionFixed = $data->user_commission_fixed;

                    if ($this->isCartProgramDisabledForInstallment($userCommissionFixed)) {
                        $userCommissionFixed = null;
                    }

                    if (is_numeric($userCommissionFixed)) {
                        $userCommissionFixed = (float) $userCommissionFixed;
                    }

                    $resource->setUserCommissionFixed($userCommissionFixed);
                }
                if (isset($data->user_commission_percentage)) {
                    $userCommissionPercentage = $data->user_commission_percentage;

                    if ($this->isCartProgramDisabledForInstallment($userCommissionPercentage)) {
                        $userCommissionPercentage = null;
                    }

                    if (is_numeric($userCommissionPercentage)) {
                        $userCommissionPercentage = (float) $userCommissionPercentage;
                    }

                    $resource->setUserCommissionPercentage($userCommissionPercentage);
                }
                if (isset($data->currency_code)) {
                    $resource->setCurrencyCode($data->currency_code);
                }
                if (isset($data->installment)) {
                    $resource->setInstallment($data->installment);
                }
                if (isset($data->pos_id)) {
                    $resource->setPosId($data->pos_id);
                }
                if (isset($data->getpos_card_program)) {
                    $resource->setGetposCardProgram($data->getpos_card_program);
                }

                $details[$installment][$index] = $resource;
            }
        }

        return $details;
    }

    /*
     * TODO: "-" ifadesi x ile aynı mı? öğrenilmeli.
     */
    private function isCartProgramDisabledForInstallment($data): bool
    {
        return 'x' === $data || 'X' === $data || '-' === $data;
    }
}
