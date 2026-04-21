<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->foreignId('box_id')->nullable()->constrained('boxes')->nullOnDelete();
            $table->string('product_type', 32)->default('trial_box');
            $table->string('box_size', 16);
            $table->json('preferences')->nullable();
            $table->json('allergies')->nullable();
            $table->string('preferred_timeslot')->nullable();
            $table->string('preferred_delivery_window')->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 32)->default('pending_payment');
            $table->date('scheduled_for_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('scheduled_for_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
