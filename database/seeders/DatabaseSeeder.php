<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Model events stay enabled on purpose: Book derives its slug and authors
     * line in a saving hook, and muting events leaves those columns null.
     */
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

        $this->call(BookSeeder::class);
    }
}
