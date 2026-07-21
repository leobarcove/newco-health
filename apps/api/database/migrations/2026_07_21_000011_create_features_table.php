<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DB-driven feature flags (dev plan §10) — no third-party flag service.
        Schema::create('features', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
