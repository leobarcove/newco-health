<?php

namespace Database\Factories;

use App\Modules\Consults\Models\Consult;
use App\Modules\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Consult> */
class ConsultFactory extends Factory
{
    protected $model = Consult::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'state' => Consult::STATE_REQUESTED,
            'modality' => 'chat',
        ];
    }

    public function queued(): static
    {
        return $this->state(['state' => Consult::STATE_QUEUED, 'queued_at' => now()]);
    }
}
