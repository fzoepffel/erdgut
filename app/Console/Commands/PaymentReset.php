<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

class PaymentReset extends Command
{
    protected $signature = 'payment:reset
                            {email? : Email of the user to reset (omit to reset all non-production users)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clear saved SumUp payment data (token, customer ID, next charge date) and delete payment rows. Safe to run on staging/local only — unless --force is used in production.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');
            $this->line('Add --force only if you are absolutely sure.');

            return self::FAILURE;
        }

        $email = $this->argument('email');

        if ($email !== null) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $this->error("No user found with email: {$email}");

                return self::FAILURE;
            }

            $subscriptions = Subscription::where('user_id', $user->id)->get();
        } else {
            if (! $this->option('force') && ! $this->confirm('No email given — reset payment data for ALL users?')) {
                $this->line('Aborted.');

                return self::SUCCESS;
            }

            $subscriptions = Subscription::all();
        }

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions found — nothing to reset.');

            return self::SUCCESS;
        }

        $subIds = $subscriptions->pluck('id');

        $paymentCount = Payment::whereIn('subscription_id', $subIds)->count();
        $subCount = $subscriptions->count();

        if (! $this->option('force')) {
            $target = $email ?? 'ALL users';
            $this->warn('This will:');
            $this->line("  • Clear sumup_token, sumup_customer_id, next_charge_date on {$subCount} subscription(s)");
            $this->line("  • Delete {$paymentCount} payment row(s)");
            $this->line("  • Target: {$target}");

            if (! $this->confirm('Continue?')) {
                $this->line('Aborted.');

                return self::SUCCESS;
            }
        }

        Payment::whereIn('subscription_id', $subIds)->delete();

        Subscription::whereIn('id', $subIds)->update([
            'sumup_token' => null,
            'sumup_customer_id' => null,
            'next_charge_date' => null,
        ]);

        $this->info("Reset {$subCount} subscription(s) and deleted {$paymentCount} payment row(s).");

        return self::SUCCESS;
    }
}
