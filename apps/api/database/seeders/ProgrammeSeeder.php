<?php

namespace Database\Seeders;

use App\Modules\Programmes\Models\Programme;
use Illuminate\Database\Seeder;

class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $programmes = [
            [
                'Hypertension Care',
                'Fortnightly blood-pressure check-ins with a doctor, medication reviews, and refill reminders — keep your numbers where they should be.',
                1_000_000, // ₦10,000/month
                14,
            ],
            [
                'Diabetes Care',
                'Regular sugar-level reviews, doctor-led medication adjustments, and diet guidance that fits Nigerian meals.',
                1_250_000, // ₦12,500/month
                14,
            ],
        ];

        foreach ($programmes as [$name, $description, $price, $cadence]) {
            Programme::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'monthly_price_kobo' => $price, 'check_in_every_days' => $cadence, 'active' => true],
            );
        }
    }
}
