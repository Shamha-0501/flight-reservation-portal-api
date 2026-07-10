<?php

namespace Database\Seeders;

use App\Models\PlatformAddon;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantAddonSetting;
use Illuminate\Database\Seeder;

class TenantAddonCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $platformAddons = PlatformAddon::query()->get()->keyBy('code');

        Tenant::query()->get()->each(function (Tenant $tenant) use ($platformAddons) {
            $legacy = TenantAddonSetting::query()->where('tenant_id', $tenant->id)->first();

            foreach ($platformAddons as $addon) {
                $legacyFields = $this->legacyFieldsForCode($addon->code);

                TenantAddon::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'addon_id' => $addon->id,
                    ],
                    [
                        'is_enabled' => $legacyFields
                            ? (bool) ($legacy?->{$legacyFields['enabled_field']} ?? false)
                            : false,
                        'display_name' => null,
                        'display_description' => null,
                        'price' => $legacyFields
                            ? ($legacy?->{$legacyFields['price_field']} ?? 0)
                            : 0,
                        'currency' => $legacy?->currency ?? 'LKR',
                    ]
                );
            }
        });
    }

    /**
     * @return array{enabled_field: string, price_field: string}
     */
    private function legacyFieldsForCode(string $code): ?array
    {
        return match ($code) {
            'sms_alert' => ['enabled_field' => 'sms_alert_enabled', 'price_field' => 'sms_alert_price'],
            'whatsapp_alert' => ['enabled_field' => 'whatsapp_alert_enabled', 'price_field' => 'whatsapp_alert_price'],
            'priority_support' => ['enabled_field' => 'priority_support_enabled', 'price_field' => 'priority_support_price'],
            'travel_insurance' => ['enabled_field' => 'travel_insurance_enabled', 'price_field' => 'travel_insurance_price'],
            'baggage_protection' => ['enabled_field' => 'baggage_protection_enabled', 'price_field' => 'baggage_protection_price'],
            'airport_assistance' => ['enabled_field' => 'airport_assistance_enabled', 'price_field' => 'airport_assistance_price'],
            'checkin_assistance' => ['enabled_field' => 'checkin_assistance_enabled', 'price_field' => 'checkin_assistance_price'],
            'disruption_support' => ['enabled_field' => 'disruption_support_enabled', 'price_field' => 'disruption_support_price'],
            'lounge_access' => ['enabled_field' => 'lounge_access_enabled', 'price_field' => 'lounge_access_price'],
            'fast_track' => ['enabled_field' => 'fast_track_enabled', 'price_field' => 'fast_track_price'],
            'priority_boarding' => ['enabled_field' => 'priority_boarding_enabled', 'price_field' => 'priority_boarding_price'],
            default => null,
        };
    }
}
