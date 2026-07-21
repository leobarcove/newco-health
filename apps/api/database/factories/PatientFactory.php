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

    /** Factory patients are "onboarded": consents granted (the common test fixture). */
    public function configure(): static
    {
        return $this->afterCreating(function (Patient $patient) {
            $ledger = app(\App\Modules\Compliance\Services\ConsentLedger::class);
            $ledger->grant($patient->user, \App\Modules\Compliance\Services\ConsentLedger::KIND_TELEMEDICINE_TERMS);
            $ledger->grant($patient->user, \App\Modules\Compliance\Services\ConsentLedger::KIND_PRIVACY_POLICY);
        });
    }

    /** A brand-new user who has not accepted anything yet. */
    public function withoutConsents(): static
    {
        return $this->afterCreating(function (Patient $patient) {
            \Illuminate\Support\Facades\DB::table('consents')->where('user_id', $patient->user_id)->delete();
        });
    }
}
