<?php

namespace Database\Seeders;

use App\Models\Policy;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $policies = [
            [
                'tenant_id' => null,
                'name' => 'Full access rights',
                'effect' => 'allow',
                'priority' => 1,
                'is_enabled' => true,
                'permission_id' => 100,
                'description' => 'Allow any action on any resource'
            ]
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Policy::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Policy::upsert($policies, [], []);
    }
}
