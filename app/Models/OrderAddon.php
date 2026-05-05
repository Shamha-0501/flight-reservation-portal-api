<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddon extends Model
{
    protected $fillable = [
        'tenant_id',
        'order_id',

        'duffel_baggage_enabled',
        'duffel_baggage_count',
        'duffel_baggage_amount',
        'duffel_baggage_currency',

        'duffel_seat_enabled',
        'duffel_seat_count',
        'duffel_seat_amount',
        'duffel_seat_currency',

        'cancellation_guarantee_enabled',
        'cancellation_guarantee_type',

        'flexible_date_change_enabled',
        'flexible_date_change_type',

        'rebooking_assistance_enabled',
        'name_correction_support_enabled',
        'schedule_change_support_enabled',

        'travel_insurance_enabled',
        'travel_insurance_plan',

        'notification_alerts_enabled',
        'sms_alerts_enabled',
        'whatsapp_alerts_enabled',

        'priority_support_enabled',
        'priority_support_type',

        'disruption_compensation_support_enabled',
        'baggage_protection_enabled',
        'airport_assistance_enabled',
        'checkin_assistance_enabled',

        'travel_connectivity_enabled',
        'travel_connectivity_type',

        'premium_airport_services_enabled',
        'premium_airport_service_type',

        'agency_addons_amount',
        'duffel_addons_amount',
        'total_addons_amount',
        'currency',
    ];

    protected $casts = [
        'duffel_baggage_enabled' => 'boolean',
        'duffel_seat_enabled' => 'boolean',
        'cancellation_guarantee_enabled' => 'boolean',
        'flexible_date_change_enabled' => 'boolean',
        'rebooking_assistance_enabled' => 'boolean',
        'name_correction_support_enabled' => 'boolean',
        'schedule_change_support_enabled' => 'boolean',
        'travel_insurance_enabled' => 'boolean',
        'notification_alerts_enabled' => 'boolean',
        'sms_alerts_enabled' => 'boolean',
        'whatsapp_alerts_enabled' => 'boolean',
        'priority_support_enabled' => 'boolean',
        'disruption_compensation_support_enabled' => 'boolean',
        'baggage_protection_enabled' => 'boolean',
        'airport_assistance_enabled' => 'boolean',
        'checkin_assistance_enabled' => 'boolean',
        'travel_connectivity_enabled' => 'boolean',
        'premium_airport_services_enabled' => 'boolean',

        'duffel_baggage_amount' => 'decimal:2',
        'duffel_seat_amount' => 'decimal:2',
        'agency_addons_amount' => 'decimal:2',
        'duffel_addons_amount' => 'decimal:2',
        'total_addons_amount' => 'decimal:2',
    ];
}