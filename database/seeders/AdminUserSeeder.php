<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's administrator account.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'gret@admin.com'],
            [
                'name' => 'Administrator',
                'password' => 'admin',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );
    }
}
