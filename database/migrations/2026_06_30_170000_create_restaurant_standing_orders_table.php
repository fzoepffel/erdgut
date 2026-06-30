<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A restaurant's recurring weekly delivery arrangement ("standing order").
 *
 * Born from a normal order whose lines were flagged weekly. The admin confirms
 * the first order once, which activates this standing order; a scheduled command
 * then materializes auto-confirmed RestaurantOrders for the following weeks.
 * Delivery details are snapshot here so a later profile edit doesn't silently
 * change recurring deliveries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_standing_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('delivery_weekday'); // 0=Sun..6=Sat (Carbon dayOfWeek)
            $table->string('delivery_street');
            $table->string('delivery_postal_code', 16);
            $table->string('delivery_city');
            $table->text('delivery_notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('locale', 5)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'restaurant_standing_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_standing_orders');
    }
};
