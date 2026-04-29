<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sweep abandoned signup-only User rows.
 *
 * W2-13: when a visitor starts the wizard (creates an account) but never
 * confirms their email and never picks a plan, the User row sticks around
 * forever. Over time these accumulate and pollute admin metrics, trial
 * conversion stats, and the Nutzer table.
 *
 * Definition of an orphan-user (all conditions must hold):
 *  - email_verified_at IS NULL
 *  - no Subscription rows
 *  - no Order rows
 *  - is_admin = false (never delete an admin even by accident)
 *  - created_at older than `--older-than-days` (default 14)
 *
 * Running this deletes the row outright. Use `--dry-run` to preview.
 *
 * Cron suggestion: daily, e.g. `0 4 * * * php artisan users:cleanup-orphans`.
 */
class CleanupOrphanUsers extends Command
{
    protected $signature = 'users:cleanup-orphans
                            {--older-than-days=14 : Minimum age of a candidate row, in days.}
                            {--dry-run : Print the rows that would be deleted without touching the DB.}';

    protected $description = 'Delete unverified User rows with no subscription and no order older than N days.';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays((int) $this->option('older-than-days'));

        $orphans = User::query()
            ->whereNull('email_verified_at')
            ->where('is_admin', false)
            ->where('created_at', '<', $cutoff)
            ->doesntHave('subscriptions')
            ->whereNotIn('id', Order::query()->select('user_id')->whereNotNull('user_id'))
            ->get(['id', 'email', 'created_at']);

        if ($orphans->isEmpty()) {
            $this->info('No orphan users found older than '.$this->option('older-than-days').' day(s).');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($orphans as $u) {
            $rows[] = [
                'id' => $u->id,
                'email' => $u->email,
                'created_at' => $u->created_at?->toDateString() ?? '—',
            ];
        }
        $this->table(['id', 'email', 'created_at'], $rows);

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no rows deleted. Re-run without --dry-run to delete '.$orphans->count().' user row(s).');

            return self::SUCCESS;
        }

        $deleted = User::query()
            ->whereIn('id', $orphans->pluck('id'))
            ->delete();

        $this->info("Deleted {$deleted} orphan user row(s).");

        return self::SUCCESS;
    }
}
