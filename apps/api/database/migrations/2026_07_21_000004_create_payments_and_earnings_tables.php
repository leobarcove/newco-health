<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->string('purpose');                    // consult (booking|subscription later)
            $table->foreignUlid('consult_id')->nullable()->constrained('consults');
            $table->unsignedBigInteger('amount_kobo');    // integer money — never floats
            $table->string('currency', 3)->default('NGN');
            $table->string('gateway');                    // fake|paystack|flutterwave
            $table->string('reference')->unique();
            $table->string('status')->default('pending'); // pending|succeeded|failed|refunded
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['consult_id']);
        });

        Schema::create('doctor_earnings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('doctor_id')->constrained('doctors');
            $table->foreignUlid('consult_id')->unique()->constrained('consults'); // one earning per consult
            $table->unsignedBigInteger('amount_kobo');
            $table->string('status')->default('pending'); // pending|paid
            $table->string('payout_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['doctor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_earnings');
        Schema::dropIfExists('payments');
    }
};
