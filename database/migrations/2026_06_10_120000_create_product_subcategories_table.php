<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_subcategories', function (Blueprint $table) {
            $table->id();
            $table->string('category');          // parent ProductCategory value
            $table->string('label_de', 120);
            $table->string('label_en', 120);
            $table->string('label_fr', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_subcategories');
    }
};
