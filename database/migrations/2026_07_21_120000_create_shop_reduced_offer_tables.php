<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Reduced offer" mode: instead of hard-closing the shop when only a few
     * repeating inflows (e.g. flour) are in stock, the storefront stays
     * shoppable but shows a banner + countdown until the full offer returns,
     * plus an email waitlist.
     *
     * State is derived live on read (see ShopReducedOfferService), mirroring
     * the closure system: a missed cron never drifts the banner. The period
     * rows exist only to make the reopen email idempotent (one send per cycle).
     */
    public function up(): void
    {
        // Recurring weekly window: reduced from starts_weekday@starts_time until
        // reopens_weekday@reopens_time each week. Typically a single active row
        // (e.g. Mon 14:00 → Fri 16:00), but several are allowed.
        Schema::create('shop_reduced_offer_rules', function (Blueprint $table) {
            $table->id();
            // Carbon dayOfWeek: 0=Sun .. 6=Sat (matches shop_closure_rules).
            $table->unsignedTinyInteger('starts_weekday');
            $table->time('starts_time');
            $table->unsignedTinyInteger('reopens_weekday');
            $table->time('reopens_time');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // A concrete reduced window. `source` distinguishes an admin-created
        // one-off ('manual') from a materialised recurring cycle ('recurring').
        // opened_at = when it actually reopened (auto at reopens_at, or earlier
        // via "open now"); notified_at = when the waitlist email went out.
        Schema::create('shop_reduced_offer_periods', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at');
            $table->dateTime('reopens_at');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->string('source', 16)->default('manual'); // manual | recurring
            $table->foreignId('rule_id')->nullable()->constrained('shop_reduced_offer_rules')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source', 'rule_id', 'starts_at']);
            $table->index('reopens_at');
        });

        // Persistent waitlist: subscribers stay on the list across weeks and are
        // notified every time the full offer returns, until they unsubscribe.
        Schema::create('shop_offer_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('unsubscribe_token', 64)->unique();
            $table->dateTime('last_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_offer_subscribers');
        Schema::dropIfExists('shop_reduced_offer_periods');
        Schema::dropIfExists('shop_reduced_offer_rules');
    }
};
