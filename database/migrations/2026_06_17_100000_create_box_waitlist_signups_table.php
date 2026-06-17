<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('box_waitlist_signups', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            // Whether the person wants to be notified when boxes launch. The
            // row exists either way (a "no" is recorded so we never re-ask at
            // the next checkout); `interested` carries the actual answer.
            $table->boolean('interested')->default(true);
            // Where the signup came from: 'homepage' or 'checkout'.
            $table->string('source')->default('homepage');
            $table->string('locale', 5)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('source');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('box_waitlist_signups');
    }
};
