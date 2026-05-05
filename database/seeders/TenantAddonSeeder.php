<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\TenantAddonSetting;

class TenantAddonSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {

            // You can customize per tenant using name/key/etc
            $config = $this->getConfigForTenant($tenant);

            TenantAddonSetting::updateOrCreate(
                ['tenant_id' => $tenant->id],
                $config
            );
        }
    }

    private function getConfigForTenant($tenant): array
    {
        switch ($tenant->name) {

            case 'SkyWay Travels':
                return [
                    'currency' => 'USD',

                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => 25,

                    'priority_support_enabled' => true,
                    'priority_support_price' => 10,

                    'cancellation_guarantee_enabled' => true,
                    'cancellation_guarantee_price' => 40,
                    'cancellation_guarantee_type' => '80_percent',

                    'sms_alert_enabled' => true,
                    'sms_alert_price' => 2,
                ];

            case 'AeroLink Agents':
                return [
                    'currency' => 'AED',

                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => 35,

                    'priority_support_enabled' => true,
                    'priority_support_price' => 15,

                    'flexible_change_enabled' => true,
                    'flexible_change_price' => 20,

                    'whatsapp_alert_enabled' => true,
                    'whatsapp_alert_price' => 3,
                ];

            case 'Global Wings Agency':
                return [
                    'currency' => 'SGD',

                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => 30,

                    'priority_support_enabled' => false,

                    'airport_assistance_enabled' => true,
                    'airport_assistance_price' => 18,

                    'lounge_access_enabled' => true,
                    'lounge_access_price' => 50,
                ];

            default:
                // fallback (for future tenants)
                return [
                    'currency' => 'USD',
                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => rand(20, 40),
                    'priority_support_enabled' => true,
                    'priority_support_price' => rand(5, 15),
                ];
        }
    }
}