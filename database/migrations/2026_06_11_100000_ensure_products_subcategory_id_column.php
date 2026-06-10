<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent guard for staging/prod where an earlier deploy recorded the
 * subcategory table migration but failed before adding products.subcategory_id
 * (orphaned migration file + tar-extract leftovers from the reverted feature).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_subcategories')) {
            return;
        }

        if (Schema::hasColumn('products', 'subcategory_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('subcategory_id')
                ->nullable()
                ->after('category')
                ->constrained('product_subcategories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'subcategory_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subcategory_id');
        });
    }
};
