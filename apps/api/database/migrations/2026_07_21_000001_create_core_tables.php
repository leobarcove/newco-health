<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Phone-first identity: patients sign in by OTP and may have no email.
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('role')->default('patient')->after('phone'); // patient|doctor|sponsor|staff
            $table->string('locale', 8)->default('en')->after('role');
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 12)->nullable();
            $table->text('medical_notes')->nullable(); // encrypted cast (PHI)
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mdcn_licence_no')->unique();
            $table->date('licence_expires_at');
            $table->string('status')->default('pending'); // pending|active|suspended
            $table->boolean('online')->default(false);
            $table->timestamps();
        });

        Schema::create('triage_intakes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->text('complaint');            // encrypted cast (PHI)
            $table->json('answers')->nullable();  // structured symptom answers
            $table->boolean('red_flag')->default(false);
            $table->timestamps();
        });

        Schema::create('consults', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained('patients');
            $table->foreignUlid('doctor_id')->nullable()->constrained('doctors');
            $table->foreignUlid('triage_intake_id')->nullable()->constrained('triage_intakes');
            $table->string('state')->default('requested');
            $table->string('modality')->default('chat'); // chat|voice|video
            $table->string('daily_room')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'queued_at']);
        });

        Schema::create('consult_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('consult_id')->constrained('consults')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users'); // null = system
            $table->string('kind')->default('text'); // text|image|voice_note|system|prescription
            $table->text('body');                    // encrypted cast (PHI)
            $table->timestamps();
            $table->index(['consult_id', 'created_at']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('event');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('consult_messages');
        Schema::dropIfExists('consults');
        Schema::dropIfExists('triage_intakes');
        Schema::dropIfExists('doctors');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('otp_codes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'locale']);
        });
    }
};
