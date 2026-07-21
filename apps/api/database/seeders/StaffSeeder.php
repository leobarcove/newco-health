<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local/staging only — creates the first backoffice login.
 * Production staff accounts are provisioned by hand with strong passwords + 2FA.
 */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        User::firstOrCreate(
            ['email' => 'admin@newco.local'],
            [
                'name' => 'Local Admin',
                'role' => User::ROLE_STAFF,
                'password' => Hash::make('password'),
            ],
        );
    }
}
