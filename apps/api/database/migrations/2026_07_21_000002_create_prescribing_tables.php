<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nigerian Essential Medicines List subset — seeded, staff-curated.
        Schema::create('formulary_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // e.g. Artemether/Lumefantrine
            $table->string('form');                    // tablet|capsule|syrup|injection|cream
            $table->string('strength')->nullable();    // e.g. 20/120 mg
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'form', 'strength']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('consult_id')->constrained('consults');
            $table->foreignUlid('doctor_id')->constrained('doctors');
            $table->foreignUlid('patient_id')->constrained('patients');
            $table->string('status')->default('issued'); // issued|dispensed|cancelled
            $table->string('pickup_code', 12)->unique();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('formulary_item_id')->constrained('formulary_items');
            $table->string('dosage');                  // e.g. 1 tablet twice daily
            $table->unsignedSmallInteger('duration_days');
            $table->text('instructions')->nullable();  // encrypted cast (PHI)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('formulary_items');
    }
};
