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
                'key' => 'customer',
                'name' => 'Customer',
                'scope' => 'tenant',
                'is_external' => false,
                'description' => 'Customer per tenant'
            ]
        ];

        $now = now();

        $roles = array_map(function ($role) use ($now) {
            return array_merge($role, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $roles);

        Role::insert($roles);
    }
}
