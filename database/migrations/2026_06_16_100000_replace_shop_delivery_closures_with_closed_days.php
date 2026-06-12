<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_closed_days', function (Blueprint $table): void {
            $table->id();
            $table->date('closed_on')->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (! Schema::hasTable('shop_delivery_closures')) {
            return;
        }

        $cutoff = Carbon::tomorrow()->toDateString();
        $closedDays = [];

        foreach (DB::table('shop_delivery_closures')->orderBy('id')->get() as $row) {
            $exceptions = Schema::hasTable('shop_delivery_closure_exceptions')
                ? DB::table('shop_delivery_closure_exceptions')
                    ->where('shop_delivery_closure_id', $row->id)
                    ->pluck('date')
                    ->map(fn (string $date): string => Carbon::parse($date)->toDateString())
                    ->all()
                : [];

            $cursor = Carbon::parse($row->starts_on)->startOfDay();
            $end = Carbon::parse($row->ends_on)->startOfDay();

            while ($cursor->lte($end)) {
                $iso = $cursor->toDateString();
                if ($iso >= $cutoff && ! in_array($iso, $exceptions, true)) {
                    $closedDays[$iso] = $row->created_by_user_id;
                }
                $cursor->addDay();
            }
        }

        foreach ($closedDays as $iso => $userId) {
            DB::table('shop_closed_days')->insert([
                'closed_on' => $iso,
                'created_by_user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('shop_delivery_closure_exceptions');
        Schema::dropIfExists('shop_delivery_closures');
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_closed_days');

        if (! Schema::hasTable('shop_delivery_closures')) {
            Schema::create('shop_delivery_closures', function (Blueprint $table): void {
                $table->id();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['starts_on', 'ends_on']);
            });
        }

        if (! Schema::hasTable('shop_delivery_closure_exceptions')) {
            Schema::create('shop_delivery_closure_exceptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('shop_delivery_closure_id');
                $table->date('date');
                $table->timestamps();
                $table->unique(['shop_delivery_closure_id', 'date'], 'sd_cl_exc_closure_date_uq');
                $table->index('date', 'sd_cl_exc_date_idx');
            });
        }
    }
};
