<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = [
            [
                'resource' => 'system',
                'group' => 'SYSTEM'
            ],
            [
                'resource' => 'pbac_actions',
                'group' => 'PBAC',
            ],
            [
                'resource' => 'pbac_resources',
                'group' => 'PBAC',
            ],
            [
                'resource' => 'pbac_permission',
                'group' => 'PBAC',
            ],
            [
                'resource' => 'pbac_roles',
                'group' => 'PBAC',
            ],
            [
                'resource' => 'pbac_user_role',
                'group' => 'PBAC',
            ],
            [
                'resource' => 'pbac_policy',
                'group' => 'PBAC',
            ],
            [
                'resource' => 'pbac_policy_subject',
                'group' => 'PBAC',
            ],
            [
                'resource' => 'pbac_policy_context',
                'group' => 'PBAC',
            ],

        ];

        Resource::upsert($resources, [], []);
    }
}
