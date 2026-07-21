<?php

namespace Database\Factories;

use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Patient;
use App\Modules\Scheduling\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(20),
            'state' => Booking::STATE_CONFIRMED,
        ];
    }
}
