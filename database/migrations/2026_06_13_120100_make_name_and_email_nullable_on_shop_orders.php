<?php

use App\Http\Requests\ShopOrderRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `name` and `email` nullable on shop_orders to support admin-created
 * manual orders where contact details may not be available (restaurant /
 * trade orders arranged by phone).
 *
 * The storefront {@see ShopOrderRequest} still requires
 * both fields; only the admin path ({@see AdminCreateShopOrderRequest}) sends
 * them as optional. All existing rows retain their values unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
