<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Support\SumUpCheckoutLogger;
use App\Support\SumUpService;
use App\Support\SumUpWebhookHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Safety net for payments whose SumUp webhook never arrived.
 *
 * Finds local Payment rows stuck in `pending` past a minimum age, asks SumUp
 * for the authoritative status of each checkout, and — when SumUp reports a
 * terminal status — invokes the same handler the webhook uses so state
 * transitions, side effects, and notifications stay consistent.
 *
 * Run from the scheduler every few minutes. Idempotent against the webhook.
 */
class ReconcilePendingSumUpPayments extends Command
{
    protected $signature = 'sumup:reconcile-pending-payments
                            {--min-age-minutes=2 : Only reconcile payments older than this many minutes}
                            {--limit=100 : Maximum number of payments to inspect per run}
                            {--payment= : Reconcile a single payment id (ignores min-age)}
                            {--dry-run : Inspect SumUp without mutating local state}';

    protected $description = 'Reconcile local Payment rows stuck in pending against SumUp (webhook safety net).';

    public function __construct(
        private readonly SumUpService $sumUpService,
        private readonly SumUpWebhookHandler $webhookHandler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('sumup_checkout_id');

        if ($paymentId = $this->option('payment')) {
            $query->where('id', (int) $paymentId);
        } else {
            $minAge = (int) $this->option('min-age-minutes');
            if ($minAge > 0) {
                $query->where('updated_at', '<=', Carbon::now()->subMinutes($minAge));
            }

            $limit = max(1, (int) $this->option('limit'));
            $query->limit($limit);
        }

        $pending = $query->orderBy('id')->get();

        if ($pending->isEmpty()) {
            $this->info('No pending SumUp payments to reconcile.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $paid = 0;
        $failed = 0;
        $stillPending = 0;
        $errors = 0;

        foreach ($pending as $payment) {
            try {
                $checkout = $this->sumUpService->getCheckout((string) $payment->sumup_checkout_id);
            } catch (Throwable $e) {
                $errors++;
                SumUpCheckoutLogger::warning('Reconcile: failed to fetch checkout from SumUp.', [
                    'payment_id' => $payment->id,
                    'checkout_id' => $payment->sumup_checkout_id,
                    'exception_class' => $e::class,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                ]);

                continue;
            }

            $status = strtoupper((string) data_get($checkout, 'status', ''));

            if (! in_array($status, ['PAID', 'FAILED', 'EXPIRED'], true)) {
                $stillPending++;
                $this->line(sprintf(
                    'Payment #%d checkout %s still %s — skipping.',
                    $payment->id,
                    $payment->sumup_checkout_id,
                    $status ?: 'UNKNOWN'
                ));

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] Payment #%d would transition to terminal state (SumUp status: %s).',
                    $payment->id,
                    $status
                ));
                $status === 'PAID' ? $paid++ : $failed++;

                continue;
            }

            // Delegate to the webhook handler so state transitions, next-charge-date
            // bumps, and notifications are all handled identically to webhook-driven flows.
            try {
                $this->webhookHandler->handleCheckoutStatusChanged([
                    'event_type' => 'CHECKOUT_STATUS_CHANGED',
                    'id' => (string) $payment->sumup_checkout_id,
                ]);

                SumUpCheckoutLogger::info('Reconcile: delegated terminal checkout to webhook handler.', [
                    'payment_id' => $payment->id,
                    'checkout_id' => $payment->sumup_checkout_id,
                    'checkout_status' => $status,
                ]);

                $status === 'PAID' ? $paid++ : $failed++;
            } catch (Throwable $e) {
                $errors++;
                SumUpCheckoutLogger::warning('Reconcile: webhook handler threw while reconciling.', [
                    'payment_id' => $payment->id,
                    'checkout_id' => $payment->sumup_checkout_id,
                    'exception_class' => $e::class,
                    'error' => SumUpCheckoutLogger::sanitizeExceptionMessage($e->getMessage()),
                ]);
            }
        }

        $this->info(sprintf(
            'Reconcile complete: inspected %d, paid %d, failed %d, still pending %d, errors %d%s.',
            $pending->count(),
            $paid,
            $failed,
            $stillPending,
            $errors,
            $dryRun ? ' (dry run)' : ''
        ));

        return self::SUCCESS;
    }
}
