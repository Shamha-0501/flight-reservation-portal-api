<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Keep the backend role catalog aligned with the frontend auth model.
        $roles = [
            [
                'tenant_id' => null,
                'key' => 'system_developer',
                'name' => 'System Developer',
                'scope' => 'global',
                'is_external' => false,
                'description' => 'System Creator'
            ],
            [
                'tenant_id' => null,
                'key' => 'super_admin',
                'name' => 'Super Admin',
                'scope' => 'global',
                'is_external' => false,
                'description' => 'Supreme access across all tenants'
            ],
            [
                'tenant_id' => null,
                'key' => 'tenant_owner',
                'name' => 'Tenant Owner',
                'scope' => 'tenant',
                'is_external' => false,
                'description' => 'Owner of the tenant / business'
            ],
            [
                'tenant_id' => null,
                'key' => 'tenant_admin',
                'name' => 'Tenant Admin',
                'scope' => 'tenant',
                'is_external' => false,
                'description' => 'Administrator of the Tenant'
            ],
            [
                'tenant_id' => null,
                'key' => 'agency_manager',
                'name' => 'Agency Manager',
                'scope' => 'tenant',
                'is_external' => false,
                'description' => 'Manages the agency workspace and team operations'
            ],
            [
                'tenant_id' => null,
                'key' => 'agency_staff',
                'name' => 'Agency Staff',
                'scope' => 'tenant',
                'is_external' => false,
                'description' => 'Handles agency workspace tasks and bookings'
            ],
            [
                'tenant_id' => null,
                'key' => 'customer',
                'name' => 'Customer',
                'scope' => 'tenant',
                'is_external' => false,
                'description' => 'Customer per tenant'
            ]
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                [
                    'tenant_id' => null,
                    'key' => $role['key'],
                ],
                [
                    'name' => $role['name'],
                    'scope' => $role['scope'],
                    'is_external' => $role['is_external'],
                    'description' => $role['description'],
                ]
            );
        }
    }
}
