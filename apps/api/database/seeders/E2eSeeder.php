<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Doctors\Models\Doctor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Fixtures for Playwright golden journeys. Never runs in production. */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $doctorUser = User::firstOrCreate(
            ['phone' => '+2348000000009'],
            ['name' => 'Amara Okafor', 'role' => User::ROLE_DOCTOR, 'password' => Str::random(40)],
        );

        Doctor::firstOrCreate(
            ['user_id' => $doctorUser->id],
            [
                'mdcn_licence_no' => 'MDCN/E2E001',
                'licence_expires_at' => now()->addYear(),
                'status' => Doctor::STATUS_ACTIVE,
                'online' => true,
            ],
        );
    }
}
