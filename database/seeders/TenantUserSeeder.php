<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tenant_user')->truncate();

        $tenantUsers = [
            [
                'tenant_name' => 'SkyWay Travels',
                'user_email' => 'shamhaasfer@gmail.com',
                'role_id' => 1,
            ],
            [
                'tenant_name' => 'AeroLink Agents',
                'user_email' => 'farvees@gmail.com',
                'role_id' => 3,
            ],
            [
                'tenant_name' => 'Global Wings Agency',
                'user_email' => 'test@gmail.com',
                'role_id' => 4,
            ],
        ];

        foreach ($tenantUsers as $item) {
            $tenant = Tenant::where('name', $item['tenant_name'])->firstOrFail();
            $user = User::where('email', $item['user_email'])->firstOrFail();

            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $item['role_id'],
                'invited_by_user_id' => null,
            ]);
        }
    }
}