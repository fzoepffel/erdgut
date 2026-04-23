<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('alt', 255)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });

        // Backfill existing products.image_path into a primary product_images row.
        $now = now();

        DB::table('products')
            ->whereNotNull('image_path')
            ->where('image_path', '<>', '')
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($now): void {
                $rows = $products->map(fn ($p) => [
                    'product_id' => $p->id,
                    'path' => $p->image_path,
                    'position' => 0,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('product_images')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
