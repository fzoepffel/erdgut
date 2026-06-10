<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Stub migration kept so this filename is present in the repo.
 *
 * The staging server still holds this file from an earlier, reverted
 * subcategory implementation (which used a separate translations table).
 * That approach was replaced by label_de / label_en / label_fr columns
 * directly on product_subcategories (see the sibling migration).
 *
 * Having this file in the repo means the deploy archive overwrites the
 * stale server copy, and `php artisan migrate` records it as done
 * without attempting the broken constraint that caused the MySQL error.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op: the translations-table approach was replaced before
        // shipping. The canonical subcategory migration is
        // 2026_06_10_120000_create_product_subcategories_table.php.
    }

    public function down(): void {}
};
