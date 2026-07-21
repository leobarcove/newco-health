<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('patient_id')->constrained('patients')->cascadeOnDelete(); // guardian
            $table->string('name');                       // PHI-adjacent; encrypted cast
            $table->string('relationship');               // child|parent|spouse|other
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 12)->nullable();
            $table->timestamps();
        });

        Schema::table('consults', function (Blueprint $table) {
            $table->foreignUlid('dependant_id')->nullable()->after('patient_id')->constrained('dependants');
        });

        // Diaspora wedge: a sponsor (payer) linked to a beneficiary (patient).
        Schema::create('sponsorships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('sponsor_user_id')->constrained('users');
            $table->foreignUlid('patient_id')->constrained('patients');
            $table->string('status')->default('pending'); // pending|active|declined|paused
            $table->string('beneficiary_label')->nullable(); // what the sponsor calls them ("Mum")
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['sponsor_user_id', 'patient_id']);
            $table->index(['patient_id', 'status']);
        });

        // Sponsor care wallet — prepaid NGN that auto-covers beneficiary fees.
        Schema::create('wallets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained();
            $table->unsignedBigInteger('balance_kobo')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('sponsorships');
        Schema::table('consults', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dependant_id');
        });
        Schema::dropIfExists('dependants');
    }
};
