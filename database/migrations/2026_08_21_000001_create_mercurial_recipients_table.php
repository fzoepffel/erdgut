<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent address book for the weekly restaurant mercurial: email
 * addresses the admin adds by hand (contacts that are NOT restaurant
 * User accounts) so they don't have to be re-typed each week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercurial_recipients', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercurial_recipients');
    }
};
