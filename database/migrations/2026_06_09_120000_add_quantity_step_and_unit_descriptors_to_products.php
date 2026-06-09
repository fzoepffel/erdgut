<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // For mass products: step in grams. For volume products: step in ml.
            // NULL = fall back to config('shop.quantity_step').
            // Ignored for piece/bunch — always 1.
            $table->unsignedInteger('quantity_step')->nullable()->after('unit');

            // For piece/bunch products: approximate weight/volume per unit.
            // Informational — shown to customers but not used for pricing or inventory.
            $table->unsignedInteger('unit_weight_grams')->nullable()->after('quantity_step');
            $table->unsignedInteger('unit_volume_ml')->nullable()->after('unit_weight_grams');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['quantity_step', 'unit_weight_grams', 'unit_volume_ml']);
        });
    }
};
