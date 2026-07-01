<?php

namespace Database\Seeders;

use App\Services\CurrencyConverter;
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\TenantAddonSetting;

class TenantAddonSeeder extends Seeder
{
    private const DEFAULT_CURRENCY = 'LKR';

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
        $converter = app(CurrencyConverter::class);

        switch ($tenant->name) {

            case 'SkyWay Travels':
                return [
                    'currency' => self::DEFAULT_CURRENCY,

                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => $converter->convertAmount(25, 'USD'),

                    'priority_support_enabled' => true,
                    'priority_support_price' => $converter->convertAmount(10, 'USD'),

                    'cancellation_guarantee_enabled' => true,
                    'cancellation_guarantee_price' => $converter->convertAmount(40, 'USD'),
                    'cancellation_guarantee_type' => '80_percent',

                    'sms_alert_enabled' => true,
                    'sms_alert_price' => $converter->convertAmount(2, 'USD'),
                ];

            case 'AeroLink Agents':
                return [
                    'currency' => self::DEFAULT_CURRENCY,

                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => $converter->convertAmount(35, 'AED'),

                    'priority_support_enabled' => true,
                    'priority_support_price' => $converter->convertAmount(15, 'AED'),

                    'flexible_change_enabled' => true,
                    'flexible_change_price' => $converter->convertAmount(20, 'AED'),

                    'whatsapp_alert_enabled' => true,
                    'whatsapp_alert_price' => $converter->convertAmount(3, 'AED'),
                ];

            case 'Global Wings Agency':
                return [
                    'currency' => self::DEFAULT_CURRENCY,

                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => $converter->convertAmount(30, 'SGD'),

                    'priority_support_enabled' => false,

                    'airport_assistance_enabled' => true,
                    'airport_assistance_price' => $converter->convertAmount(18, 'SGD'),

                    'lounge_access_enabled' => true,
                    'lounge_access_price' => $converter->convertAmount(50, 'SGD'),
                ];

            default:
                // fallback (for future tenants)
                return [
                    'currency' => self::DEFAULT_CURRENCY,
                    'travel_insurance_enabled' => true,
                    'travel_insurance_price' => $converter->convertAmount(rand(20, 40), 'USD'),
                    'priority_support_enabled' => true,
                    'priority_support_price' => $converter->convertAmount(rand(5, 15), 'USD'),
                ];
        }
    }
}
