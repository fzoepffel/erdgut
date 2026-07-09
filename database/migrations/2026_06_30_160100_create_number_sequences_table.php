<?php

use App\Models\NumberSequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic gap-free counter, keyed by an arbitrary string (e.g.
 * "restaurant_invoice:2026"). {@see NumberSequence::next()} increments
 * a row under lockForUpdate() inside the caller's transaction, so allocated
 * numbers are sequential and never duplicated under concurrency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->unsignedInteger('value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
