<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Translate the existing weekly inventory into daily inflows / schedules.
 *
 * Each `product_week_stocks` row becomes one inflow with
 * `received_on = week_start_date`, `quantity = planned_quantity`,
 * `freshness_window_days = 7`. The 7-day freshness preserves the
 * legacy "Monday stock is good all week" semantics so callers that
 * query availability for any date within the week still find it.
 *
 * Each `product_week_stock_schedules` row becomes a
 * `product_stock_inflow_schedules` row with `weekday = 0` (Monday),
 * `starts_on = starts_on_monday`, `ends_on = ends_on_monday`,
 * `freshness_window_days = 7`.
 *
 * The inflow.source_schedule_id back-pointer is remapped from the
 * old schedule id to the new one via an in-memory lookup built
 * while migrating schedules.
 *
 * Idempotent: a `[migrated:wsid=<old_id>]` marker on the inflow's
 * `notes` column lets reruns skip already-migrated rows. Same
 * marker on schedule notes for the schedule side.
 *
 * The legacy tables are NOT dropped here — Phase 6 will drop them
 * once Phases 1–5 are stable on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // 1. Schedules first so inflows can reference the new ids.
            $scheduleIdMap = $this->migrateSchedules();

            // 2. Inflows — each weekly stock row becomes one inflow.
            $this->migrateWeeklyStocks($scheduleIdMap);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('product_stock_inflows')
                ->where('notes', 'like', '[migrated:wsid=%]')
                ->delete();

            DB::table('product_stock_inflow_schedules')
                ->where('notes', 'like', '[migrated:wsid=%]')
                ->delete();
        });
    }

    /**
     * @return array<int, int> map of old schedule id → new schedule id
     */
    private function migrateSchedules(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('product_week_stock_schedules')) {
            return [];
        }

        $map = [];
        $now = now();

        $rows = DB::table('product_week_stock_schedules')->orderBy('id')->get();
        foreach ($rows as $row) {
            $marker = '[migrated:wsid='.$row->id.']';
            $existing = DB::table('product_stock_inflow_schedules')
                ->where('notes', 'like', '%'.$marker.'%')
                ->value('id');
            if ($existing !== null) {
                $map[(int) $row->id] = (int) $existing;

                continue;
            }

            $newId = DB::table('product_stock_inflow_schedules')->insertGetId([
                'product_id' => $row->product_id,
                'weekday' => 0, // Monday
                'planned_quantity' => $row->planned_quantity,
                'freshness_window_days' => 7,
                'starts_on' => $row->starts_on_monday,
                'ends_on' => $row->ends_on_monday,
                'notes' => trim(($row->notes ?? '').' '.$marker),
                'created_by_user_id' => $row->created_by_user_id,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $now,
            ]);

            $map[(int) $row->id] = (int) $newId;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $scheduleIdMap
     */
    private function migrateWeeklyStocks(array $scheduleIdMap): void
    {
        if (! DB::getSchemaBuilder()->hasTable('product_week_stocks')) {
            return;
        }

        $now = now();

        DB::table('product_week_stocks')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($scheduleIdMap, $now): void {
                foreach ($rows as $row) {
                    $marker = '[migrated:wsid='.$row->id.']';

                    $existing = DB::table('product_stock_inflows')
                        ->where('notes', 'like', '%'.$marker.'%')
                        ->exists();
                    if ($existing) {
                        continue;
                    }

                    $sourceScheduleId = null;
                    if ($row->source_schedule_id !== null) {
                        $sourceScheduleId = $scheduleIdMap[(int) $row->source_schedule_id] ?? null;
                    }

                    DB::table('product_stock_inflows')->insert([
                        'product_id' => $row->product_id,
                        'received_on' => $row->week_start_date,
                        'quantity' => $row->planned_quantity,
                        'freshness_window_days' => 7,
                        'source_schedule_id' => $sourceScheduleId,
                        'notes' => $marker,
                        'created_by_user_id' => null,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }
};
