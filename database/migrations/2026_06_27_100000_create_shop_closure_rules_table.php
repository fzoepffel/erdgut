<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurring auto-closure rules: "close delivery weekday X automatically
     * N days before, at a set time." Closures themselves are computed on read
     * from these rules (see ShopClosureService) — no rows are materialized.
     */
    public function up(): void
    {
        Schema::create('shop_closure_rules', function (Blueprint $table) {
            $table->id();
            // Carbon dayOfWeek: 0=Sun .. 6=Sat (matches config/delivery allowed_weekdays).
            $table->unsignedTinyInteger('weekday')->unique();
            // How many days before the delivery day the cutoff falls (Wed→Mon = 2).
            $table->unsignedSmallInteger('lead_days');
            // Clock time on the cutoff day at/after which the delivery day closes.
            $table->time('cutoff_time');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_closure_rules');
    }
};
