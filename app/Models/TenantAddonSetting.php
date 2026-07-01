<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\CurrencyConverter;

class TenantAddonSetting extends Model
{
    protected $table = 'tenant_addon_settings';

    protected $fillable = [
        'tenant_id',

        // Cancellation & Changes
        'cancellation_guarantee_enabled',
        'cancellation_guarantee_price',
        'cancellation_guarantee_type',

        'flexible_change_enabled',
        'flexible_change_price',

        'rebooking_assistance_enabled',
        'rebooking_assistance_price',

        'name_correction_enabled',
        'name_correction_price',

        // Protection & Add-ons
        'travel_insurance_enabled',
        'travel_insurance_price',

        'priority_support_enabled',
        'priority_support_price',

        'sms_alert_enabled',
        'sms_alert_price',

        'whatsapp_alert_enabled',
        'whatsapp_alert_price',

        'airport_assistance_enabled',
        'airport_assistance_price',

        'checkin_assistance_enabled',
        'checkin_assistance_price',

        'baggage_protection_enabled',
        'baggage_protection_price',

        'disruption_support_enabled',
        'disruption_support_price',

        // Premium
        'lounge_access_enabled',
        'lounge_access_price',

        'fast_track_enabled',
        'fast_track_price',

        'priority_boarding_enabled',
        'priority_boarding_price',

        // General
        'currency',
    ];

    protected $casts = [
        // Booleans
        'cancellation_guarantee_enabled' => 'boolean',
        'flexible_change_enabled' => 'boolean',
        'rebooking_assistance_enabled' => 'boolean',
        'name_correction_enabled' => 'boolean',

        'travel_insurance_enabled' => 'boolean',
        'priority_support_enabled' => 'boolean',
        'sms_alert_enabled' => 'boolean',
        'whatsapp_alert_enabled' => 'boolean',
        'airport_assistance_enabled' => 'boolean',
        'checkin_assistance_enabled' => 'boolean',
        'baggage_protection_enabled' => 'boolean',
        'disruption_support_enabled' => 'boolean',

        'lounge_access_enabled' => 'boolean',
        'fast_track_enabled' => 'boolean',
        'priority_boarding_enabled' => 'boolean',

        // Prices
        'cancellation_guarantee_price' => 'decimal:2',
        'flexible_change_price' => 'decimal:2',
        'rebooking_assistance_price' => 'decimal:2',
        'name_correction_price' => 'decimal:2',

        'travel_insurance_price' => 'decimal:2',
        'priority_support_price' => 'decimal:2',
        'sms_alert_price' => 'decimal:2',
        'whatsapp_alert_price' => 'decimal:2',
        'airport_assistance_price' => 'decimal:2',
        'checkin_assistance_price' => 'decimal:2',
        'baggage_protection_price' => 'decimal:2',
        'disruption_support_price' => 'decimal:2',

        'lounge_access_price' => 'decimal:2',
        'fast_track_price' => 'decimal:2',
        'priority_boarding_price' => 'decimal:2',
    ];

    public function getCurrencyAttribute($value): string
    {
        return $value ?: config('finance.default_currency', 'LKR');
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function calculateTotal(array $selected): float
    {
        $total = 0;
        $currency = $this->getRawOriginal('currency') ?? config('finance.default_currency', 'LKR');
        $converter = app(CurrencyConverter::class);

        foreach ($selected as $key) {
            $priceField = "{$key}_price";
            $enabledField = "{$key}_enabled";

            if ($this->$enabledField && $this->$priceField) {
                $total += (float) $converter->convertAmount($this->getRawOriginal($priceField) ?? $this->$priceField, $currency);
            }
        }

        return $total;
    }
}
