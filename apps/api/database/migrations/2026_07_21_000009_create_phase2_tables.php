<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payout destination details (collected at doctor onboarding).
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('timezone');
            $table->string('bank_account_last4', 4)->nullable()->after('bank_name');
            $table->string('paystack_recipient_code')->nullable()->after('bank_account_last4');
        });

        // Employers/HMO groups as PAYERS, not tenants (dev plan §16 tenancy posture).
        Schema::create('organisations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->string('status')->default('active'); // active|suspended
            $table->unsignedBigInteger('balance_kobo')->default(0); // prepaid float
            $table->timestamps();
        });

        Schema::create('organisation_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients');
            $table->string('status')->default('active'); // active|ended
            $table->timestamps();
            $table->unique(['organisation_id', 'patient_id']);
        });

        // Chronic-care programmes (startup plan §4 wedge 2 — doctor-led, not coaching).
        Schema::create('programmes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description');
            $table->unsignedBigInteger('monthly_price_kobo');
            $table->unsignedSmallInteger('check_in_every_days')->default(14);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('programme_enrolments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('programme_id')->constrained('programmes');
            $table->foreignUlid('patient_id')->constrained('patients');
            $table->string('status')->default('active'); // active|lapsed|cancelled
            $table->timestamp('current_period_ends_at');
            $table->timestamp('next_check_in_at');
            $table->timestamp('last_nudged_at')->nullable();
            $table->timestamps();
            $table->unique(['programme_id', 'patient_id']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUlid('programme_enrolment_id')->nullable()->after('booking_id')->constrained('programme_enrolments');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropConstrainedForeignId('programme_enrolment_id'));
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('programme_enrolments');
        Schema::dropIfExists('programmes');
        Schema::dropIfExists('organisation_memberships');
        Schema::dropIfExists('organisations');
        Schema::table('doctors', fn (Blueprint $t) => $t->dropColumn(['bank_name', 'bank_account_last4', 'paystack_recipient_code']));
    }
};
