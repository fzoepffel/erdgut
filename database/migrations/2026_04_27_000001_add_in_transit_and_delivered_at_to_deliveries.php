<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds operational timestamps to the `deliveries` table.
 *
 * Until now the table only tracked `status` (current state) and Laravel's
 * `created_at` / `updated_at`. This left support without an answer to
 * "when was delivery #X actually marked delivered?" — the only way to
 * recover that was log scraping.
 *
 * `in_transit_at` and `delivered_at` are nullable timestamps stamped once
 * each on the corresponding terminal transition. They never get reset:
 * if an admin flips status back from `delivered` to `in_transit` (rare),
 * the original `delivered_at` is preserved as the canonical record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->timestamp('in_transit_at')->nullable()->after('status');
            $table->timestamp('delivered_at')->nullable()->after('in_transit_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropColumn(['in_transit_at', 'delivered_at']);
        });
    }
};
