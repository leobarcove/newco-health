<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // Template times are local to the doctor; storage of instants is UTC.
            $table->string('timezone', 64)->default('Africa/Lagos')->after('online');
        });

        // Recurring weekly availability, e.g. "Mondays 09:00–13:00, 20-minute slots".
        Schema::create('availability_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');       // ISO: 1 = Monday … 7 = Sunday
            $table->time('start_time');                   // doctor-local
            $table->time('end_time');                     // doctor-local
            $table->unsignedSmallInteger('slot_minutes')->default(20);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['doctor_id', 'weekday']);
        });

        // Date-specific overrides: a day off, or extra ad-hoc hours.
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->date('date');                         // doctor-local calendar date
            $table->string('kind');                       // unavailable | extra
            $table->time('start_time')->nullable();       // null on 'unavailable' = whole day
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('slot_minutes')->default(20);
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['doctor_id', 'date']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained('patients');
            $table->foreignUlid('doctor_id')->constrained('doctors');
            $table->foreignUlid('consult_id')->nullable()->constrained('consults');
            $table->foreignUlid('rescheduled_from_id')->nullable()->constrained('bookings');
            $table->timestamp('starts_at');               // UTC instant
            $table->timestamp('ends_at');                 // UTC instant
            $table->string('state')->default('confirmed'); // confirmed|completed|cancelled|no_show
            $table->text('complaint')->nullable();        // encrypted cast (PHI)
            $table->string('cancelled_by')->nullable();   // patient|doctor|staff|system
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_1h_at')->nullable();
            $table->timestamps();
            $table->index(['doctor_id', 'starts_at']);
            $table->index(['patient_id', 'starts_at']);
            $table->index(['state', 'starts_at']);
        });

        // Hard guarantee against double-booking at the storage layer (Postgres).
        // SQLite (tests) relies on the transactional guard in BookingService.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX bookings_no_double_booking
                 ON bookings (doctor_id, starts_at)
                 WHERE state = \'confirmed\''
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_templates');
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
