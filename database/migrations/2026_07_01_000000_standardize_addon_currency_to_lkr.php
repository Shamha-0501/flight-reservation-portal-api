<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\CurrencyConverter;

return new class extends Migration
{
    public function up(): void
    {
        $converter = app(CurrencyConverter::class);

        $rows = DB::table('tenant_addon_settings')
            ->join('tenants', 'tenant_addon_settings.tenant_id', '=', 'tenants.id')
            ->select('tenant_addon_settings.*', 'tenants.name as tenant_name')
            ->get();

        foreach ($rows as $row) {
            $sourceCurrency = match ($row->tenant_name) {
                'SkyWay Travels' => 'USD',
                'AeroLink Agents' => 'AED',
                'Global Wings Agency' => 'SGD',
                default => 'USD',
            };

            DB::table('tenant_addon_settings')
                ->where('id', $row->id)
                ->update([
                    'currency' => 'LKR',
                    'cancellation_guarantee_price' => $converter->convertAmount($row->cancellation_guarantee_price, $sourceCurrency),
                    'flexible_change_price' => $converter->convertAmount($row->flexible_change_price, $sourceCurrency),
                    'rebooking_assistance_price' => $converter->convertAmount($row->rebooking_assistance_price, $sourceCurrency),
                    'name_correction_price' => $converter->convertAmount($row->name_correction_price, $sourceCurrency),
                    'travel_insurance_price' => $converter->convertAmount($row->travel_insurance_price, $sourceCurrency),
                    'priority_support_price' => $converter->convertAmount($row->priority_support_price, $sourceCurrency),
                    'sms_alert_price' => $converter->convertAmount($row->sms_alert_price, $sourceCurrency),
                    'whatsapp_alert_price' => $converter->convertAmount($row->whatsapp_alert_price, $sourceCurrency),
                    'airport_assistance_price' => $converter->convertAmount($row->airport_assistance_price, $sourceCurrency),
                    'checkin_assistance_price' => $converter->convertAmount($row->checkin_assistance_price, $sourceCurrency),
                    'baggage_protection_price' => $converter->convertAmount($row->baggage_protection_price, $sourceCurrency),
                    'disruption_support_price' => $converter->convertAmount($row->disruption_support_price, $sourceCurrency),
                    'lounge_access_price' => $converter->convertAmount($row->lounge_access_price, $sourceCurrency),
                    'fast_track_price' => $converter->convertAmount($row->fast_track_price, $sourceCurrency),
                    'priority_boarding_price' => $converter->convertAmount($row->priority_boarding_price, $sourceCurrency),
                ]);
        }

        DB::table('order_addons')
            ->whereNull('currency')
            ->update([
                'currency' => 'LKR',
            ]);
    }

    public function down(): void
    {
        // Currency values are historical business data; do not roll them back.
    }
};
