<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browser-side telemetry rows from the SumUp card widget.
 *
 * Each row is a single phase transition or stall event beaconed up from
 * the customer's browser. We use this to diagnose widget failures that
 * never surface as server errors — e.g. the friend on /e/OS Murena whose
 * 3DS redirect never fired (2026-05-05). The widget itself only knew it
 * was stuck in `processing`; without this table we have no record at all.
 *
 * Rows are short-lived (default 30 days; rotate via cron / TRUNCATE) and
 * intentionally carry no PII. The `meta` JSON holds capability flags,
 * viewport, and any error payload from the widget's onResponse callback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_diagnostics', function (Blueprint $table): void {
            $table->id();
            $table->string('phase', 64)->index();
            $table->string('checkout_id', 128)->nullable()->index();
            $table->string('intent', 32)->nullable();
            $table->unsignedBigInteger('spargel_order_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedInteger('ms_since_mount')->nullable();
            $table->string('ua_summary', 160)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_diagnostics');
    }
};
