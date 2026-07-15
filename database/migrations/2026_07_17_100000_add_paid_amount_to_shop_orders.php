<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin order editing: track what was actually collected so an edited,
 * already-paid order can show an open remainder (or a refund due).
 *
 * paid_amount_eur stays null for never-paid orders AND for legacy paid
 * orders until their first edit lazily snapshots the pre-edit total
 * (no backfill needed; readers fall back to total_amount_eur via
 * ShopOrder::effectivePaidAmountEur()).
 *
 * outstanding_settled_amount_eur is signed: positive = remainder was
 * collected in person, negative = overpayment was refunded in person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->decimal('paid_amount_eur', 10, 2)->nullable()->after('total_amount_eur');
            $table->decimal('outstanding_settled_amount_eur', 10, 2)->nullable()->after('paid_amount_eur');
            $table->timestamp('outstanding_settled_at')->nullable()->after('outstanding_settled_amount_eur');
            $table->foreignId('outstanding_settled_by_user_id')->nullable()->after('outstanding_settled_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outstanding_settled_by_user_id');
            $table->dropColumn(['paid_amount_eur', 'outstanding_settled_amount_eur', 'outstanding_settled_at']);
        });
    }
};
