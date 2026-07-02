<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track invoice settlement: the admin marks a Rechnung as paid so open money
 * is visible in the queue (there is no PSP on B2B invoices — payment arrives
 * by bank transfer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_orders', 'invoice_paid_at')) {
                $table->timestamp('invoice_paid_at')->nullable()->after('invoice_due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table): void {
            $table->dropColumn('invoice_paid_at');
        });
    }
};
