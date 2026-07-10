<?php

namespace Database\Seeders;

use App\Models\PlatformAddon;
use App\Support\AddonCatalog;
use Illuminate\Database\Seeder;

class PlatformAddonSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AddonCatalog::defaults() as $addon) {
            PlatformAddon::updateOrCreate(
                ['code' => $addon['code']],
                [
                    'default_name' => $addon['default_name'],
                    'default_description' => $addon['default_description'],
                    'category' => $addon['category'],
                    'is_active' => $addon['is_active'],
                    'sort_order' => $addon['sort_order'],
                ]
            );
        }
    }
}
