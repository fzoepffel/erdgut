<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trial-shop fine-tune: customer can opt to pay in person at pickup time
 * instead of going through the SumUp online card flow. Admin then marks
 * the order paid manually via the standalone /spargel-admin view.
 *
 * - `payment_method` distinguishes the two channels ('online' vs
 *   'in_person'). Defaults to 'online' for parity with rows created
 *   before this migration ran.
 *
 * - `marked_paid_by_admin_at` records when an admin pressed the
 *   "mark paid" button — separate from `paid_at` (which the SumUp
 *   return-URL flow already sets) so we can tell the two channels
 *   apart in the trial-shop log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->string('payment_method', 16)->default('online')->after('total_amount_eur');
            $table->timestamp('marked_paid_by_admin_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'marked_paid_by_admin_at']);
        });
    }
};
