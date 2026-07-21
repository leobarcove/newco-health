<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Compliance\Services\ConsentLedger;
use App\Modules\Consults\Models\Consult;
use App\Modules\Consults\Models\ConsultMessage;
use App\Modules\Consults\Models\ConsultNote;
use App\Modules\Consults\Models\TriageIntake;
use App\Modules\Doctors\Models\Doctor;
use App\Modules\Patients\Models\Dependant;
use App\Modules\Patients\Models\Patient;
use App\Modules\Patients\Models\Sponsorship;
use App\Modules\Payments\Models\Wallet;
use App\Modules\Prescribing\Models\FormularyItem;
use App\Modules\Prescribing\Models\Prescription;
use App\Modules\Scheduling\Models\AvailabilityTemplate;
use App\Modules\Scheduling\Models\Booking;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A living local dataset: every screen has something to show the moment you
 * sign in. Local/staging only — never production.
 *
 * Sign-ins (with OTP_TEST_CODE=000000 in .env, any code below works):
 *   Patient  +2348011111111 · has dependants, sponsor, live consult, booking
 *   Patient  +2348022222222 · waiting in the queue
 *   Doctor   +2348099999991 · Dr Amara Okafor — in consult, has earnings
 *   Doctor   +2348099999992 · Dr Tunde Bakare — free, sees the queue
 *   Sponsor  sponsor@newco.local / sponsorpass  · funded wallet, one beneficiary
 *   Staff    admin@newco.local / password       · Filament at /admin
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $consents = app(ConsentLedger::class);

        // — Doctors with weekly availability —
        $amara = $this->doctor('Amara Okafor', '+2348099999991', 'MDCN/100001');
        $tunde = $this->doctor('Tunde Bakare', '+2348099999992', 'MDCN/100002');
        $this->doctor('Ngozi Eze', '+2348099999993', 'MDCN/100003', expiringSoon: true);

        foreach ([$amara, $tunde] as $doctor) {
            foreach ([1, 2, 3, 4, 5] as $weekday) { // Mon–Fri
                AvailabilityTemplate::firstOrCreate(
                    ['doctor_id' => $doctor->id, 'weekday' => $weekday, 'start_time' => '09:00'],
                    ['end_time' => '13:00', 'slot_minutes' => 20, 'active' => true],
                );
            }
        }

        // — Patients —
        $bisi = $this->patient('Bisi Adewale', '+2348011111111', $consents);
        $chuka = $this->patient('Chuka Obi', '+2348022222222', $consents);
        $this->patient('Funke Alabi', '+2348033333333', $consents);

        Dependant::firstOrCreate(
            ['patient_id' => $bisi->id, 'relationship' => 'parent'],
            ['name' => 'Mama Ronke', 'date_of_birth' => '1958-03-14', 'sex' => 'female'],
        );
        Dependant::firstOrCreate(
            ['patient_id' => $bisi->id, 'relationship' => 'child'],
            ['name' => 'Damilola', 'date_of_birth' => '2019-11-02', 'sex' => 'male'],
        );

        // — Sponsor with funded wallet, sponsoring Bisi —
        $sponsor = User::firstOrCreate(
            ['email' => 'sponsor@newco.local'],
            ['name' => 'Ngozi in Houston', 'role' => User::ROLE_SPONSOR, 'password' => Hash::make('sponsorpass')],
        );
        Wallet::firstOrCreate(['user_id' => $sponsor->id], ['balance_kobo' => 1_000_000]); // ₦10,000
        $sponsorship = Sponsorship::firstOrCreate(
            ['sponsor_user_id' => $sponsor->id, 'patient_id' => $bisi->id],
            ['status' => Sponsorship::STATUS_ACTIVE, 'beneficiary_label' => 'Mum', 'responded_at' => now()],
        );
        if ($sponsorship->wasRecentlyCreated) {
            $consents->grant($bisi->user, ConsentLedger::KIND_SPONSOR_VISIBILITY);
        }

        if (Consult::count() > 0) {
            return; // consult fixtures only on a fresh database
        }

        // — A live consult: Bisi ↔ Dr Amara, mid-conversation —
        $live = $this->consult($bisi, $amara, Consult::STATE_IN_CONSULT, 'Fever and headache since Monday');
        foreach ([
            [null, 'You are in the queue. A doctor will be with you shortly — we will notify you.', ConsultMessage::KIND_SYSTEM],
            [null, 'Dr Amara Okafor has joined your consult.', ConsultMessage::KIND_SYSTEM],
            [$amara->user_id, 'Good afternoon Bisi. How high has the fever been, and any chills?', ConsultMessage::KIND_TEXT],
            [$bisi->user_id, 'Around 38.5 last night. Chills yes, and my head aches badly.', ConsultMessage::KIND_TEXT],
        ] as [$sender, $body, $kind]) {
            ConsultMessage::create(['consult_id' => $live->id, 'sender_id' => $sender, 'kind' => $kind, 'body' => $body]);
        }
        ConsultNote::create([
            'consult_id' => $live->id,
            'doctor_id' => $amara->id,
            'subjective' => 'Fever 38.5°C, chills, frontal headache × 3 days. No vomiting.',
            'assessment' => 'Probable uncomplicated malaria.',
        ]);

        // — A waiting consult: Chuka in the queue —
        $this->consult($chuka, null, Consult::STATE_QUEUED, 'Persistent dry cough for two weeks');

        // — A concluded consult with a prescription —
        $done = $this->consult($bisi, $amara, Consult::STATE_CONCLUDED, 'Rash on both arms');
        $prescription = Prescription::create([
            'consult_id' => $done->id,
            'doctor_id' => $amara->id,
            'patient_id' => $bisi->id,
            'status' => Prescription::STATUS_ISSUED,
            'pickup_code' => 'RX-SAMPLE23',
        ]);
        $prescription->items()->create([
            'formulary_item_id' => FormularyItem::where('name', 'Loratadine')->first()->id,
            'dosage' => '1 tablet at night',
            'duration_days' => 7,
            'instructions' => 'Avoid the soap you changed to last month',
        ]);
        ConsultMessage::create([
            'consult_id' => $done->id, 'sender_id' => $amara->user_id,
            'kind' => ConsultMessage::KIND_PRESCRIPTION, 'body' => $prescription->id,
        ]);

        // — A pharmacy with a counter login (pharmacy@newco.local / pharmacypass) —
        $pharmacy = \App\Modules\Prescribing\Models\Pharmacy::firstOrCreate(
            ['pcn_licence_no' => 'PCN/DEV001'],
            ['name' => 'HealthPlus Yaba', 'phone' => '+2348044444444', 'address' => '23 Herbert Macaulay Way, Yaba', 'status' => 'active'],
        );
        User::firstOrCreate(
            ['email' => 'pharmacy@newco.local'],
            ['name' => 'HealthPlus Yaba counter', 'role' => User::ROLE_PHARMACY, 'pharmacy_id' => $pharmacy->id, 'password' => Hash::make('pharmacypass')],
        );

        // — An upcoming confirmed booking: Bisi with Dr Tunde —
        $slot = now()->addDay()->setTime(9, 0); // 10:00 Lagos
        Booking::create([
            'patient_id' => $bisi->id,
            'doctor_id' => $tunde->id,
            'starts_at' => $slot,
            'ends_at' => $slot->copy()->addMinutes(20),
            'state' => Booking::STATE_CONFIRMED,
            'complaint' => 'Follow-up on blood pressure readings',
        ]);
    }

    private function doctor(string $name, string $phone, string $licence, bool $expiringSoon = false): Doctor
    {
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => $name, 'role' => User::ROLE_DOCTOR, 'password' => Str::random(40)],
        );

        return Doctor::firstOrCreate(
            ['user_id' => $user->id],
            [
                'mdcn_licence_no' => $licence,
                'licence_expires_at' => $expiringSoon ? now()->addDays(30) : now()->addYear(),
                'status' => Doctor::STATUS_ACTIVE,
                'online' => true,
            ],
        );
    }

    private function patient(string $name, string $phone, ConsentLedger $consents): Patient
    {
        $user = User::firstOrCreate(
            ['phone' => $phone],
            ['name' => $name, 'role' => User::ROLE_PATIENT, 'password' => Str::random(40)],
        );

        $patient = Patient::firstOrCreate(['user_id' => $user->id]);

        if ($patient->wasRecentlyCreated) {
            $consents->grant($user, ConsentLedger::KIND_TELEMEDICINE_TERMS);
            $consents->grant($user, ConsentLedger::KIND_PRIVACY_POLICY);
        }

        return $patient;
    }

    private function consult(Patient $patient, ?Doctor $doctor, string $state, string $complaint): Consult
    {
        $intake = TriageIntake::create([
            'patient_id' => $patient->id, 'complaint' => $complaint, 'answers' => [], 'red_flag' => false,
        ]);

        return Consult::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor?->id,
            'triage_intake_id' => $intake->id,
            'state' => $state,
            'queued_at' => in_array($state, [Consult::STATE_QUEUED, Consult::STATE_IN_CONSULT, Consult::STATE_CONCLUDED], true) ? now()->subMinutes(20) : null,
            'assigned_at' => $doctor !== null ? now()->subMinutes(10) : null,
            'concluded_at' => $state === Consult::STATE_CONCLUDED ? now()->subMinutes(2) : null,
        ]);
    }
}
