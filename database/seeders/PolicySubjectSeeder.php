<?php

namespace Database\Seeders;

use App\Models\PolicySubject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PolicySubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $policy_subjects = [
            [
                'tenant_id' => null,
                'policy_id' => 1,
                'subject_type' => 'role',
                'subject_id' => 1,
            ]
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PolicySubject::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        PolicySubject::upsert($policy_subjects, [], []);
    }
}
