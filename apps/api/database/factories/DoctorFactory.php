<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Doctors\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Doctor> */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state([
                'role' => User::ROLE_DOCTOR,
                'phone' => '+234'.$this->faker->unique()->numerify('##########'),
            ]),
            'mdcn_licence_no' => 'MDCN/'.$this->faker->unique()->numerify('######'),
            'licence_expires_at' => now()->addYear(),
            'status' => Doctor::STATUS_ACTIVE,
            'online' => true,
        ];
    }

    public function expiredLicence(): static
    {
        return $this->state(['licence_expires_at' => now()->subDay()]);
    }
}
