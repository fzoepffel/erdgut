<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('allergens')->nullable()->after('metadata');
            $table->json('dietary_tags')->nullable()->after('allergens');
            $table->json('taste_profile_tags')->nullable()->after('dietary_tags');
            $table->unsignedInteger('buying_price_cents')->nullable()->after('taste_profile_tags');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'allergens',
                'dietary_tags',
                'taste_profile_tags',
                'buying_price_cents',
            ]);
        });
    }
};
