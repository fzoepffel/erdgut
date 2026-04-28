<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Click-destination instrumentation for the customer dashboard.
     *
     * The followup doc on dashboard architecture says "instrument first,
     * redesign later" — this is the storage. Each row is one click on a
     * tracked element. We aggregate later in a query / view; nothing in
     * the app reads from this table on the hot path.
     *
     * Retention: 90 days, swept by the dashboard:purge-click-events
     * command on the daily scheduler.
     */
    public function up(): void
    {
        Schema::create('dashboard_click_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Conceptual region of the dashboard the click came from.
            // Free-form short string so we can add new sections without a
            // migration; capped at 50 to keep the index slim.
            $table->string('section', 50);
            // Destination identifier — usually a route name like
            // 'app.subscriptions.show', or a fragment like
            // 'almanach.box:42' when no canonical route fits.
            $table->string('destination', 100);
            $table->string('locale', 5)->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['section', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_click_events');
    }
};
