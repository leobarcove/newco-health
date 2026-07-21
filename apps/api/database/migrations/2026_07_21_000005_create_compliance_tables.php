<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: who read which PHI, when, from where (NDPA accountability).
        Schema::create('phi_access_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('label');                 // e.g. consult.messages.read
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        // Append-only event ledger: current state = latest row per (user, kind).
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('kind');                  // telemedicine_terms|privacy_policy|sponsor_visibility
            $table->string('action');                // granted|revoked
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();        // policy version, locale shown, …
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        Schema::dropIfExists('phi_access_log');
    }
};
