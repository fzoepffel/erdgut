<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proper Rechnungen for shop orders: any non-mirror order can be invoiced
 * with a number from the SAME yearly ledger as restaurant invoices (one
 * gap-free EB sequence). Payment tracking stays on the order's existing
 * paid/outstanding columns; no invoice_paid_at here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('invoice_number', 32)->nullable()->unique()->after('receipt_overrides');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_number');
            $table->date('invoice_due_date')->nullable()->after('invoiced_at');
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'invoiced_at', 'invoice_due_date']);
        });
    }
};
