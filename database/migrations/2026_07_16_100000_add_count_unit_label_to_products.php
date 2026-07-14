<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional display word for Piece products (pot, bag, jar, ...): NULL
 * keeps today's "Stück". Values come from App\Enums\CountUnitLabel;
 * display-only, the unit math is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('count_unit_label', 20)->nullable()->after('unit_volume_ml');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('count_unit_label');
        });
    }
};
