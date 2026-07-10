<?php

use App\Support\AddonCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $catalog = collect(AddonCatalog::defaults())->keyBy('code');
        $fieldMap = AddonCatalog::legacyTenantFieldMap();
        $tenants = DB::table('tenant_addon_settings')->get();

        foreach ($tenants as $settings) {
            foreach ($fieldMap as $code => $map) {
                $addon = $catalog->get($code);
                if (! $addon) {
                    continue;
                }

                $enabledField = $map['enabled_field'];
                $priceField = $map['price_field'];

                DB::table('tenant_addons')->updateOrInsert(
                    [
                        'tenant_id' => $settings->tenant_id,
                        'addon_id' => $addon['id'],
                    ],
                    [
                        'is_enabled' => (bool) ($settings->{$enabledField} ?? false),
                        'display_name' => null,
                        'display_description' => null,
                        'price' => $settings->{$priceField} ?? 0,
                        'currency' => $settings->currency ?? 'LKR',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('tenant_addons')->truncate();
    }
};
