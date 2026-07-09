<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recurring lines of a standing order (the weekly subset of the original
 * order). Each line carries its own active window: it is included in a week's
 * generated order when starts_on <= delivery_date <= (ends_on ?? indefinite).
 * One-time lines from the original order are NOT stored here.
 *
 * `quantity` is in canonical units (g/ml/piece/bunch), mirroring
 * restaurant_order_items, so the same submit() payload shape can be rebuilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The auto-generated FK name for restaurant_standing_order_id is 68
        // chars; MySQL caps identifiers at 64. The first production run died
        // mid-create on that ALTER, leaving the table without any FKs and the
        // migration unrecorded. Environments where this migration IS recorded
        // (staging, dev) never re-enter up(), so dropping the empty torso here
        // is safe and lets the rerun rebuild the table cleanly.
        Schema::dropIfExists('restaurant_standing_order_items');

        Schema::create('restaurant_standing_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_standing_order_id');
            $table->foreign('restaurant_standing_order_id', 'rsoi_standing_order_fk')
                ->references('id')->on('restaurant_standing_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('variant_name_snapshot')->nullable();
            $table->string('name_snapshot', 160)->nullable(); // free-form special line
            $table->string('unit')->nullable();
            $table->decimal('quantity', 10, 4);
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // null = indefinite
            $table->timestamps();

            $table->index('restaurant_standing_order_id', 'restaurant_standing_items_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_standing_order_items');
    }
};
