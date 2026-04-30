<?php

namespace Database\Seeders;

use App\Models\Action;
use App\Models\Permission;
use App\Models\Resource;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = Action::all();
        $resources = Resource::all();

        $permissions = [];

        foreach($actions as $action) {
            foreach($resources as $resource) {
                $permissions[] = [
                    'action_id' => $action->id,
                    'resource_id' => $resource->id,
                    'key' => "$action->action:$resource->resource",
                    'label' => "$action->action $resource->resource resource"
                ];
            }
        }

        Permission::upsert($permissions, [], []);
    }
}
