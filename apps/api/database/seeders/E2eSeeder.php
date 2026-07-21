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

        $doctor = Doctor::firstOrCreate(
            ['user_id' => $doctorUser->id],
            [
                'mdcn_licence_no' => 'MDCN/E2E001',
                'licence_expires_at' => now()->addYear(),
                'status' => Doctor::STATUS_ACTIVE,
                'online' => true,
            ],
        );

        // Full-week, near-full-day availability so "Tomorrow" always has slots
        // whatever real time the e2e run starts at.
        foreach (range(1, 7) as $weekday) {
            \App\Modules\Scheduling\Models\AvailabilityTemplate::firstOrCreate(
                ['doctor_id' => $doctor->id, 'weekday' => $weekday, 'start_time' => '00:00'],
                ['end_time' => '23:40', 'slot_minutes' => 20, 'active' => true],
            );
        }

        $this->call(FormularySeeder::class);

        $pharmacy = \App\Modules\Prescribing\Models\Pharmacy::firstOrCreate(
            ['pcn_licence_no' => 'PCN/E2E001'],
            ['name' => 'E2E Pharmacy', 'status' => 'active'],
        );
        User::firstOrCreate(
            ['email' => 'pharmacy@e2e.local'],
            ['name' => 'E2E counter', 'role' => User::ROLE_PHARMACY, 'pharmacy_id' => $pharmacy->id, 'password' => \Illuminate\Support\Facades\Hash::make('pharmacypass123')],
        );
    }
}
