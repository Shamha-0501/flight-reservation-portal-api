<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\Role;
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
                'role_key' => 'system_developer',
            ],
            [
                'tenant_name' => 'SkyWay Travels',
                'user_email' => 'farvees@gmail.com',
                'role_key' => 'tenant_owner',
            ],
            [
                'tenant_name' => 'SkyWay Travels',
                'user_email' => 'test@gmail.com',
                'role_key' => 'tenant_admin',
            ],
        ];

        foreach ($tenantUsers as $item) {
            $tenant = Tenant::where('name', $item['tenant_name'])->firstOrFail();
            $user = User::where('email', $item['user_email'])->firstOrFail();
            $roleId = Role::where('key', $item['role_key'])->value('id');

            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $roleId,
                'invited_by_user_id' => null,
                'status' => 'active',
            ]);
        }
    }
}
