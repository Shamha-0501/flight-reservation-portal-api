<?php

namespace Database\Seeders;

use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user_roles = [
            [
                'tenant_id' => null,
                'user_id' => 1,
                'role_id' => 1,
                'status' => 'active',
                'expires_at' => null
            ],
            [
                'tenant_id' => null,
                'user_id' => 2,
                'role_id' => 6,
                'status' => 'active',
                'expires_at' => null
            ],
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        UserRole::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        UserRole::upsert($user_roles, [], []);
    }
}
