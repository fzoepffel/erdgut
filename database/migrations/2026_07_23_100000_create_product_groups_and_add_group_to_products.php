<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product groups: several full Products (each with its own unit, price,
 * producer, stock and rules) bundled into ONE display card with a grade
 * selector, for offerings that are "the same thing" from different farms,
 * possibly sold in different units (kg vs Stück). The group carries its
 * OWN name (e.g. "Apfelsäfte"); members contribute a short grade label.
 * Cart/order lines stay keyed (product_id, variant_id) — nothing in the
 * order machinery changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // localized {de,en,fr}; de required
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('producer_id')
                ->constrained('product_groups')->nullOnDelete();
            // Short grade label shown in the selector ("dick", "Hof Sonnen-
            // acker", "0,5 l Flasche"); empty falls back to the producer name.
            $table->json('group_label')->nullable()->after('group_id');
            $table->unsignedSmallInteger('group_position')->default(0)->after('group_label');

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn(['group_label', 'group_position']);
        });
        Schema::dropIfExists('product_groups');
    }
};
