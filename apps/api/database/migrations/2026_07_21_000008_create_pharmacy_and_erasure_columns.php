<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('pcn_licence_no')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('state_of_operation')->default('Lagos');
            $table->string('status')->default('active'); // active|suspended
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('pharmacy_id')->nullable()->after('locale')->constrained('pharmacies');
            $table->timestamp('erased_at')->nullable()->after('remember_token'); // NDPA erasure marker
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignUlid('pharmacy_id')->nullable()->after('doctor_id')->constrained('pharmacies');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pharmacy_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pharmacy_id');
            $table->dropColumn('erased_at');
        });
        Schema::dropIfExists('pharmacies');
    }
};
