<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Fathima Shamha',
                'email' => 'shamhaasfer@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Sara',
                'email' => 'Sara@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Test User',
                'email' => 'test@gmail.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
        ];

        $now = now();

        $users = array_map(function ($user) use ($now) {
            return array_merge($user, [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $users);

        User::insert($users);
    }
}
