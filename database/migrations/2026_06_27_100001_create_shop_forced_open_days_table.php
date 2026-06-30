<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual "keep this day open" overrides. Beats a recurring closure rule
     * for that specific date (manual always wins). Mirrors shop_closed_days
     * (which is the manual force-CLOSED override).
     */
    public function up(): void
    {
        Schema::create('shop_forced_open_days', function (Blueprint $table) {
            $table->id();
            $table->date('forced_open_on')->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_forced_open_days');
    }
};
