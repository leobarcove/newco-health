<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One SOAP-lite note per consult, doctor-only, all PHI encrypted.
        Schema::create('consult_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('consult_id')->unique()->constrained('consults')->cascadeOnDelete();
            $table->foreignUlid('doctor_id')->constrained('doctors');
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUlid('booking_id')->nullable()->after('consult_id')->constrained('bookings');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
        Schema::dropIfExists('consult_notes');
    }
};
