<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'SkyWay Travels',
                'status' => 'active',
                'timezone' => 'Asia/Colombo',
                'locale' => 'en',
                'created_by_user_id' => null,
                'trial_ends_at' => now()->addDays(14),
                'suspended_at' => null,
                'meta' => [
                    'industry' => 'flight_booking',
                    'contact_email' => 'info@skywaytravels.com',
                    'contact_phone' => '+94112345678',
                    'country' => 'Sri Lanka',
                ],
            ],
            [
                'name' => 'AeroLink Agents',
                'status' => 'active',
                'timezone' => 'Asia/Dubai',
                'locale' => 'en',
                'created_by_user_id' => null,
                'trial_ends_at' => now()->addDays(7),
                'suspended_at' => null,
                'meta' => [
                    'industry' => 'flight_booking',
                    'contact_email' => 'support@aerolink.ae',
                    'contact_phone' => '+971501234567',
                    'country' => 'UAE',
                ],
            ],
            [
                'name' => 'Global Wings Agency',
                'status' => 'active',
                'timezone' => 'Asia/Singapore',
                'locale' => 'en',
                'created_by_user_id' => null,
                'trial_ends_at' => now()->addDays(30),
                'suspended_at' => null,
                'meta' => [
                    'industry' => 'flight_booking',
                    'contact_email' => 'contact@globalwings.sg',
                    'contact_phone' => '+6561234567',
                    'country' => 'Singapore',
                ],
            ],
        ];

        foreach ($tenants as $tenant) {
            Tenant::create($tenant);
        }
    }
}