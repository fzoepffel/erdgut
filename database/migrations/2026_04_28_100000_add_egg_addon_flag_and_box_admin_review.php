<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three columns supporting the per-customer composition workflow:
     *
     * 1. products.is_egg_addon — when true, the product is excluded from the
     *    standard size template auto-distribution and only surfaces in the
     *    eggs add-on slot (separate panel per customer).
     *
     * 2. boxes.composition_admin_ok_at + composition_admin_ok_by — capture
     *    the "Als OK markieren" admin acknowledgement so reopening the
     *    setup page in edit mode shows the box as already-OK rather than
     *    re-flagging it.
     *
     * 3. boxes.eggs_addon_unfulfilled_at — when admin saves a box for a
     *    customer with the eggs add-on but no eggs product was added, mark
     *    the box and trigger the partial refund flow.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_egg_addon')->default(false)->after('is_published')
                ->comment('Filtered out of standard box composition; only surfaces in the eggs add-on slot');
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->timestamp('composition_admin_ok_at')->nullable()->after('next_update_expected_at')
                ->comment('When the admin marked this box as reviewed during setup');
            $table->foreignId('composition_admin_ok_by')->nullable()->after('composition_admin_ok_at')
                ->constrained('users')->nullOnDelete()
                ->comment('Admin user who acknowledged');
            $table->timestamp('eggs_addon_unfulfilled_at')->nullable()->after('composition_admin_ok_by')
                ->comment('Customer ordered the eggs add-on but the box was saved without one — refund + notify');
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropForeign(['composition_admin_ok_by']);
            $table->dropColumn(['composition_admin_ok_at', 'composition_admin_ok_by', 'eggs_addon_unfulfilled_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_egg_addon');
        });
    }
};
