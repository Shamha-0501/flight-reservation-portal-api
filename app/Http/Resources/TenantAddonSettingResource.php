<?php

namespace App\Http\Resources;

use App\Services\CurrencyConverter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantAddonSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tenant_id' => $this->tenant_id,

            'cancellation_guarantee' => [
                'enabled' => $this->cancellation_guarantee_enabled,
                'price' => $this->moneyAmount('cancellation_guarantee_price'),
                'type' => $this->cancellation_guarantee_type,
            ],

            'flexible_change' => [
                'enabled' => $this->flexible_change_enabled,
                'price' => $this->moneyAmount('flexible_change_price'),
            ],

            'rebooking_assistance' => [
                'enabled' => $this->rebooking_assistance_enabled,
                'price' => $this->moneyAmount('rebooking_assistance_price'),
            ],

            'name_correction' => [
                'enabled' => $this->name_correction_enabled,
                'price' => $this->moneyAmount('name_correction_price'),
            ],

            'travel_insurance' => [
                'enabled' => $this->travel_insurance_enabled,
                'price' => $this->moneyAmount('travel_insurance_price'),
            ],

            'priority_support' => [
                'enabled' => $this->priority_support_enabled,
                'price' => $this->moneyAmount('priority_support_price'),
            ],

            'sms_alert' => [
                'enabled' => $this->sms_alert_enabled,
                'price' => $this->moneyAmount('sms_alert_price'),
            ],

            'whatsapp_alert' => [
                'enabled' => $this->whatsapp_alert_enabled,
                'price' => $this->moneyAmount('whatsapp_alert_price'),
            ],

            'airport_assistance' => [
                'enabled' => $this->airport_assistance_enabled,
                'price' => $this->moneyAmount('airport_assistance_price'),
            ],

            'checkin_assistance' => [
                'enabled' => $this->checkin_assistance_enabled,
                'price' => $this->moneyAmount('checkin_assistance_price'),
            ],

            'baggage_protection' => [
                'enabled' => $this->baggage_protection_enabled,
                'price' => $this->moneyAmount('baggage_protection_price'),
            ],

            'disruption_support' => [
                'enabled' => $this->disruption_support_enabled,
                'price' => $this->moneyAmount('disruption_support_price'),
            ],

            'lounge_access' => [
                'enabled' => $this->lounge_access_enabled,
                'price' => $this->moneyAmount('lounge_access_price'),
            ],

            'fast_track' => [
                'enabled' => $this->fast_track_enabled,
                'price' => $this->moneyAmount('fast_track_price'),
            ],

            'priority_boarding' => [
                'enabled' => $this->priority_boarding_enabled,
                'price' => $this->moneyAmount('priority_boarding_price'),
            ],

            'currency' => $this->currencyCode(),
        ];
    }

    private function moneyAmount(string $amountKey): ?string
    {
        $amount = $this->getRawOriginal($amountKey) ?? $this->{$amountKey};

        return app(CurrencyConverter::class)->convertAmount($amount, $this->currencyCode());
    }

    private function currencyCode(): string
    {
        return config('finance.default_currency', 'LKR');
    }
}
