<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-added mercurial contacts need a restaurant name of their own: the
 * `name` column is the contact person (used for the {{name}} greeting), so
 * without this the {{restaurant}} placeholder had nothing meaningful to fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mercurial_recipients', function (Blueprint $table): void {
            $table->string('restaurant')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('mercurial_recipients', function (Blueprint $table): void {
            $table->dropColumn('restaurant');
        });
    }
};
