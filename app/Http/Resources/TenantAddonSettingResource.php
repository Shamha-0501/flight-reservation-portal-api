<?php

namespace App\Http\Resources;

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
                'price' => $this->cancellation_guarantee_price,
                'type' => $this->cancellation_guarantee_type,
            ],

            'flexible_change' => [
                'enabled' => $this->flexible_change_enabled,
                'price' => $this->flexible_change_price,
            ],

            'rebooking_assistance' => [
                'enabled' => $this->rebooking_assistance_enabled,
                'price' => $this->rebooking_assistance_price,
            ],

            'name_correction' => [
                'enabled' => $this->name_correction_enabled,
                'price' => $this->name_correction_price,
            ],

            'travel_insurance' => [
                'enabled' => $this->travel_insurance_enabled,
                'price' => $this->travel_insurance_price,
            ],

            'priority_support' => [
                'enabled' => $this->priority_support_enabled,
                'price' => $this->priority_support_price,
            ],

            'sms_alert' => [
                'enabled' => $this->sms_alert_enabled,
                'price' => $this->sms_alert_price,
            ],

            'whatsapp_alert' => [
                'enabled' => $this->whatsapp_alert_enabled,
                'price' => $this->whatsapp_alert_price,
            ],

            'airport_assistance' => [
                'enabled' => $this->airport_assistance_enabled,
                'price' => $this->airport_assistance_price,
            ],

            'checkin_assistance' => [
                'enabled' => $this->checkin_assistance_enabled,
                'price' => $this->checkin_assistance_price,
            ],

            'baggage_protection' => [
                'enabled' => $this->baggage_protection_enabled,
                'price' => $this->baggage_protection_price,
            ],

            'disruption_support' => [
                'enabled' => $this->disruption_support_enabled,
                'price' => $this->disruption_support_price,
            ],

            'lounge_access' => [
                'enabled' => $this->lounge_access_enabled,
                'price' => $this->lounge_access_price,
            ],

            'fast_track' => [
                'enabled' => $this->fast_track_enabled,
                'price' => $this->fast_track_price,
            ],

            'priority_boarding' => [
                'enabled' => $this->priority_boarding_enabled,
                'price' => $this->priority_boarding_price,
            ],

            'currency' => $this->currency,
        ];
    }
}