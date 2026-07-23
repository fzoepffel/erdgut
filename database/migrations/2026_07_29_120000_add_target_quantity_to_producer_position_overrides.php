<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producer_position_overrides', function (Blueprint $table): void {
            // The position amount in the TARGET product's CANONICAL unit, set
            // when moving to a different unit needs an admin-decided conversion
            // (e.g. 2 bunches → 1 pot, or a 250 g Schale → a 500 g Schale).
            // NULL means "no conversion": the read paths fall back to the source
            // quantity, which stays correct for a same-unit / same-farm move.
            $table->decimal('target_quantity', 12, 4)->nullable()->after('target_producer_id');
        });
    }

    public function down(): void
    {
        Schema::table('producer_position_overrides', function (Blueprint $table): void {
            $table->dropColumn('target_quantity');
        });
    }
};
