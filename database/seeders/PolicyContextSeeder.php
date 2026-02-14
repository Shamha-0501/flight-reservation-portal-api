<?php

namespace Database\Seeders;

use App\Models\PolicyContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PolicyContextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $policy_contexts = [
            [
                'tenant_id' => null,
                'policy_id' => 1,
                'scope' => 'resource',
                'left_operand' => 'resource.id',
                'operator' => '=',
                'right_type' => 'value',
                'right_ref' => '',
                'right_value_string' => null,
                'right_value_int' => 1,
                'right_value_bool' => null,
                'right_value_decimal' => null
            ]
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PolicyContext::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        PolicyContext::upsert($policy_contexts, [], []);
    }
}
