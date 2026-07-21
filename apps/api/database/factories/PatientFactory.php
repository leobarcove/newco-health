<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state([
                'role' => User::ROLE_PATIENT,
                'phone' => '+234'.$this->faker->unique()->numerify('##########'),
            ]),
        ];
    }
}
