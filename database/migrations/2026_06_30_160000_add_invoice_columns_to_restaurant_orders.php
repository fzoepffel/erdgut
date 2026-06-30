<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice (Rechnung) state on a restaurant order. `invoice_number` is the lock
 * flag — once set, the order's lines/prices are frozen (an immutable, sequentially
 * numbered B2B invoice has been issued). The unique index is the DB-level backstop
 * against a duplicate number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->string('invoice_number', 32)->nullable()->unique()->after('vat_total_eur');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_number');
            $table->date('invoice_due_date')->nullable()->after('invoiced_at');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'invoiced_at', 'invoice_due_date']);
        });
    }
};
