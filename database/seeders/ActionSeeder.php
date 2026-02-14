<?php

namespace Database\Seeders;

use App\Models\Action;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = [
            [
                'action' => 'all',
                'label' => 'All operation'
            ],
            [
                'action' => 'list',
                'label' => 'List resource'
            ],
            [
                'action' => 'view',
                'label' => 'View resource'
            ],
            [
                'action' => 'create',
                'label' => 'Create resource'
            ],
            [
                'action' => 'update',
                'label' => 'Update resource'
            ],
            [
                'action' => 'delete',
                'label' => 'Delete resource'
            ],
            [
                'action' => 'restore',
                'label' => 'Restore deleted record from resource'
            ],
            [
                'action' => 'forceDelete',
                'label' => 'Physically delete record from resource'
            ],
            [
                'action' => 'import',
                'label' => 'Import selected resource'
            ],
            [
                'action' => 'export',
                'label' => 'Export selected resource'
            ],
            [
                'action' => 'backup',
                'label' => 'Backup resource'
            ],
            [
                'action' => 'policy_bypass',
                'label' => 'Bypass policy on resource'
            ]
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Action::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Action::upsert($actions, [], []);
    }
}
