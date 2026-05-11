<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\ProductWeekStockSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recurring weekly-stock for individual products.
 *
 * Admin sets "10 kg/week from May 4, indefinitely" once; this service
 * materializes a `ProductWeekStock` row for every Monday in the window
 * AND remembers the schedule so a weekly cron can extend the window as
 * time passes.
 *
 * Read paths are unchanged: every consumer (box-setup, weekly catalog
 * admin, spargel form) keeps querying `product_week_stocks` by Monday.
 * The schedule is invisible to them.
 *
 * Manual overrides are preserved across cancel/adjust: a row with a
 * `source_schedule_id` but a `planned_quantity` that differs from the
 * schedule's current value is treated as admin-edited and left alone.
 */
final class RecurringStockService
{
    /**
     * Default materialization horizon: how many weeks ahead an
     * indefinite schedule should write rows for, on every save and
     * on every cron tick.
     */
    public const MATERIALIZE_WEEKS_AHEAD = 26;

    /**
     * Wider safety horizon used by the cron — gives the materializer
     * a 4-week buffer over the on-save horizon so the window can't
     * silently shrink between cron runs.
     */
    public const CRON_MATERIALIZE_WEEKS_AHEAD = 30;

    public function createSchedule(
        Product $product,
        float $plannedQuantity,
        Carbon $startsOnMonday,
        ?Carbon $endsOnMonday = null,
        ?int $createdByUserId = null,
        ?string $notes = null,
    ): ProductWeekStockSchedule {
        $start = $this->snapToMonday($startsOnMonday);
        $end = $endsOnMonday !== null ? $this->snapToMonday($endsOnMonday) : null;

        if ($end !== null && $end->lt($start)) {
            throw new \InvalidArgumentException('ends_on_monday must be ≥ starts_on_monday.');
        }

        return DB::transaction(function () use ($product, $plannedQuantity, $start, $end, $createdByUserId, $notes): ProductWeekStockSchedule {
            $schedule = ProductWeekStockSchedule::query()->create([
                'product_id' => $product->id,
                'planned_quantity' => round($plannedQuantity, 2),
                'starts_on_monday' => $start->toDateString(),
                'ends_on_monday' => $end?->toDateString(),
                'notes' => $notes,
                'created_by_user_id' => $createdByUserId,
            ]);

            $this->materializeSchedule($schedule, $this->defaultHorizon($start));

            return $schedule;
        });
    }

    /**
     * End a schedule effective from `$fromMonday`. Schedule's
     * `ends_on_monday` is set to the Monday before; any future
     * materialized rows from this schedule with `planned_quantity`
     * still matching the schedule are deleted. Rows that differ
     * (= admin-edited manual overrides) are left intact.
     */
    public function cancelFrom(int $scheduleId, Carbon $fromMonday): void
    {
        $from = $this->snapToMonday($fromMonday);

        DB::transaction(function () use ($scheduleId, $from): void {
            $schedule = ProductWeekStockSchedule::query()->lockForUpdate()->findOrFail($scheduleId);
            $newEnd = $from->copy()->subWeek();

            // If cancel is at or before schedule start, the schedule
            // effectively never applied — just delete it (and any rows
            // it managed to write) wholesale.
            if ($newEnd->lt($schedule->starts_on_monday)) {
                ProductWeekStock::query()
                    ->where('source_schedule_id', $schedule->id)
                    ->where('planned_quantity', $schedule->planned_quantity)
                    ->delete();
                $schedule->delete();

                return;
            }

            $schedule->forceFill(['ends_on_monday' => $newEnd->toDateString()])->save();

            ProductWeekStock::query()
                ->where('source_schedule_id', $schedule->id)
                ->whereDate('week_start_date', '>=', $from->toDateString())
                ->where('planned_quantity', $schedule->planned_quantity)
                ->delete();
        });
    }

    /**
     * Change a schedule's quantity from `$fromMonday` onward.
     * Implementation: end the old schedule the week before, then
     * create a fresh schedule starting on `$fromMonday`.
     */
    public function adjustFrom(
        int $scheduleId,
        Carbon $fromMonday,
        float $newQuantity,
        ?int $createdByUserId = null,
    ): ProductWeekStockSchedule {
        $from = $this->snapToMonday($fromMonday);

        return DB::transaction(function () use ($scheduleId, $from, $newQuantity, $createdByUserId): ProductWeekStockSchedule {
            $old = ProductWeekStockSchedule::query()->lockForUpdate()->findOrFail($scheduleId);

            $originalEnd = $old->ends_on_monday;

            $this->cancelFrom($old->id, $from);

            return $this->createSchedule(
                product: $old->product,
                plannedQuantity: $newQuantity,
                startsOnMonday: $from,
                endsOnMonday: $originalEnd,
                createdByUserId: $createdByUserId,
                notes: $old->notes,
            );
        });
    }

    /**
     * Idempotent materialization sweep. For every currently-active
     * schedule, upsert ProductWeekStock rows up to `$upTo` (inclusive).
     * Never overwrites a row whose `source_schedule_id` is null or
     * differs (= manual override).
     *
     * Returns the number of rows written (inserted or quantity-corrected).
     */
    public function materializeWindow(Carbon $upTo): int
    {
        $upToMonday = $this->snapToMonday($upTo);
        $written = 0;

        ProductWeekStockSchedule::query()
            ->where(function ($q) use ($upToMonday): void {
                $q->whereNull('ends_on_monday')->orWhereDate('ends_on_monday', '>=', $upToMonday->toDateString());
            })
            ->orderBy('id')
            ->chunk(100, function ($schedules) use ($upToMonday, &$written): void {
                foreach ($schedules as $schedule) {
                    $written += $this->materializeSchedule($schedule, $upToMonday);
                }
            });

        return $written;
    }

    /**
     * Materialize `$schedule` up to `$horizon` (inclusive). Idempotent.
     */
    private function materializeSchedule(ProductWeekStockSchedule $schedule, Carbon $horizon): int
    {
        $start = Carbon::parse($schedule->starts_on_monday);
        $end = $schedule->ends_on_monday !== null
            ? Carbon::parse($schedule->ends_on_monday)->min($horizon)
            : $horizon;

        if ($start->gt($end)) {
            return 0;
        }

        $written = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $cursorDate = $cursor->toDateString();
            $existing = ProductWeekStock::query()
                ->where('product_id', $schedule->product_id)
                ->whereDate('week_start_date', $cursorDate)
                ->first();

            if ($existing === null) {
                ProductWeekStock::create([
                    'product_id' => $schedule->product_id,
                    'week_start_date' => $cursorDate,
                    'planned_quantity' => $schedule->planned_quantity,
                    'source_schedule_id' => $schedule->id,
                ]);
                $written++;
            } elseif ($existing->source_schedule_id === null) {
                // Manual one-off row — leave it alone (admin set it
                // independently). The schedule is informational here.
            } elseif ((int) $existing->source_schedule_id === (int) $schedule->id) {
                // Our own row. Update quantity if the schedule's
                // current value differs AND the row hasn't been
                // manually edited (= still matches what we wrote).
                // We can't tell "edited vs. legitimate stale" without
                // a marker, so the conservative choice is: leave it.
                // The adjust/cancel flows handle quantity changes.
            }

            $cursor->addWeek();
        }

        return $written;
    }

    /**
     * Snap a date to its ISO Monday at midnight.
     */
    private function snapToMonday(Carbon $date): Carbon
    {
        return $date->copy()->startOfDay()->startOfWeek(Carbon::MONDAY);
    }

    private function defaultHorizon(Carbon $start): Carbon
    {
        $now = Carbon::now()->startOfDay()->startOfWeek(Carbon::MONDAY);

        return $now->max($start)->copy()->addWeeks(self::MATERIALIZE_WEEKS_AHEAD);
    }
}
