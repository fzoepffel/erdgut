<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receiving checklist for goods coming in from a producer. One row means
 * "we received this product (variant) from this farm for this delivery
 * day". Presence of the row is the checked state; created_at is when it
 * was ticked off. This is a pure operational record and does NOT feed
 * inventory or availability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // 0 = no variant. Kept non-null so the unique index below works
            // reliably in MySQL (NULLs are treated as distinct there).
            $table->unsignedBigInteger('variant_id')->default(0);
            $table->date('delivery_date');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['producer_id', 'product_id', 'variant_id', 'delivery_date'],
                'producer_receipts_unique',
            );
            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_receipts');
    }
};
