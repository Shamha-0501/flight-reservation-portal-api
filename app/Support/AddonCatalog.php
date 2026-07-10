<?php

namespace App\Support;

final class AddonCatalog
{
    /**
     * Fixed platform add-on catalog mirrored by the frontend defaults.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            [
                'id' => 1,
                'code' => 'sms_alert',
                'default_name' => 'SMS Alert',
                'default_description' => 'Receive SMS notifications about booking updates and travel reminders.',
                'category' => 'Updates',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 2,
                'code' => 'whatsapp_alert',
                'default_name' => 'WhatsApp Alert',
                'default_description' => 'Get ticket updates and trip reminders on WhatsApp.',
                'category' => 'Updates',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'id' => 3,
                'code' => 'email_itinerary',
                'default_name' => 'Email Itinerary Copy',
                'default_description' => 'Send a branded itinerary copy directly to the customer email.',
                'category' => 'Updates',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'id' => 4,
                'code' => 'priority_support',
                'default_name' => 'Priority Support',
                'default_description' => 'Offer faster booking and post-booking support.',
                'category' => 'Support',
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'id' => 5,
                'code' => 'after_hours_support',
                'default_name' => 'After Hours Support',
                'default_description' => 'Provide support outside normal working hours.',
                'category' => 'Support',
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'id' => 6,
                'code' => 'cancellation_assistance',
                'default_name' => 'Cancellation Assistance',
                'default_description' => 'Help customers handle airline cancellation steps and follow-up.',
                'category' => 'Support',
                'is_active' => true,
                'sort_order' => 60,
            ],
            [
                'id' => 7,
                'code' => 'reschedule_assistance',
                'default_name' => 'Reschedule Assistance',
                'default_description' => 'Assist customers with airline itinerary changes and repricing.',
                'category' => 'Support',
                'is_active' => true,
                'sort_order' => 70,
            ],
            [
                'id' => 8,
                'code' => 'name_correction_support',
                'default_name' => 'Name Correction Support',
                'default_description' => 'Support minor passenger-name corrections where permitted.',
                'category' => 'Identity',
                'is_active' => true,
                'sort_order' => 80,
            ],
            [
                'id' => 9,
                'code' => 'travel_insurance',
                'default_name' => 'Travel Insurance',
                'default_description' => 'Sell insurance coverage for selected trip disruptions and emergencies.',
                'category' => 'Protection',
                'is_active' => true,
                'sort_order' => 90,
            ],
            [
                'id' => 10,
                'code' => 'baggage_protection',
                'default_name' => 'Baggage Protection',
                'default_description' => 'Offer support for baggage claim assistance and protection.',
                'category' => 'Protection',
                'is_active' => true,
                'sort_order' => 100,
            ],
            [
                'id' => 11,
                'code' => 'flight_delay_support',
                'default_name' => 'Flight Delay Support',
                'default_description' => 'Support customers when flights are delayed or disrupted.',
                'category' => 'Protection',
                'is_active' => true,
                'sort_order' => 110,
            ],
            [
                'id' => 12,
                'code' => 'airport_assistance',
                'default_name' => 'Airport Assistance',
                'default_description' => 'Arrange support for a smoother airport experience.',
                'category' => 'Airport',
                'is_active' => true,
                'sort_order' => 120,
            ],
            [
                'id' => 13,
                'code' => 'checkin_assistance',
                'default_name' => 'Check-in Assistance',
                'default_description' => 'Help prepare boarding details and check-in reminders.',
                'category' => 'Airport',
                'is_active' => true,
                'sort_order' => 130,
            ],
            [
                'id' => 14,
                'code' => 'emergency_hotline',
                'default_name' => 'Emergency Hotline',
                'default_description' => 'Provide urgent travel support through an emergency hotline.',
                'category' => 'Emergency',
                'is_active' => true,
                'sort_order' => 140,
            ],
        ];
    }

    /**
     * @return array<string, array{legacy_field: string, enabled_field: string, price_field: string}>
     */
    public static function legacyTenantFieldMap(): array
    {
        return [
            'sms_alert' => [
                'legacy_field' => 'sms_alert',
                'enabled_field' => 'sms_alert_enabled',
                'price_field' => 'sms_alert_price',
            ],
            'whatsapp_alert' => [
                'legacy_field' => 'whatsapp_alert',
                'enabled_field' => 'whatsapp_alert_enabled',
                'price_field' => 'whatsapp_alert_price',
            ],
            'priority_support' => [
                'legacy_field' => 'priority_support',
                'enabled_field' => 'priority_support_enabled',
                'price_field' => 'priority_support_price',
            ],
            'travel_insurance' => [
                'legacy_field' => 'travel_insurance',
                'enabled_field' => 'travel_insurance_enabled',
                'price_field' => 'travel_insurance_price',
            ],
            'baggage_protection' => [
                'legacy_field' => 'baggage_protection',
                'enabled_field' => 'baggage_protection_enabled',
                'price_field' => 'baggage_protection_price',
            ],
            'airport_assistance' => [
                'legacy_field' => 'airport_assistance',
                'enabled_field' => 'airport_assistance_enabled',
                'price_field' => 'airport_assistance_price',
            ],
            'checkin_assistance' => [
                'legacy_field' => 'checkin_assistance',
                'enabled_field' => 'checkin_assistance_enabled',
                'price_field' => 'checkin_assistance_price',
            ],
            'disruption_support' => [
                'legacy_field' => 'disruption_support',
                'enabled_field' => 'disruption_support_enabled',
                'price_field' => 'disruption_support_price',
            ],
            'lounge_access' => [
                'legacy_field' => 'lounge_access',
                'enabled_field' => 'lounge_access_enabled',
                'price_field' => 'lounge_access_price',
            ],
            'fast_track' => [
                'legacy_field' => 'fast_track',
                'enabled_field' => 'fast_track_enabled',
                'price_field' => 'fast_track_price',
            ],
            'priority_boarding' => [
                'legacy_field' => 'priority_boarding',
                'enabled_field' => 'priority_boarding_enabled',
                'price_field' => 'priority_boarding_price',
            ],
        ];
    }
}
