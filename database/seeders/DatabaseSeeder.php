<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name'  => 'admin',
            'email' => 'admin@mail.com',
            'role'  => UserRole::Admin,
        ]);
        User::factory()->create([
            'name'  => 'client',
            'email' => 'client@mail.com',
            'role'  => UserRole::Client,
        ]);
    }
}
