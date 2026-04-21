<?php

namespace App\Console\Commands;

use App\Models\OnboardingDraft;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneOnboardingDrafts extends Command
{
    protected $signature = 'onboarding:prune-drafts';

    protected $description = 'Delete onboarding drafts whose expires_at has passed.';

    public function handle(): int
    {
        $now = Carbon::now();

        $deleted = OnboardingDraft::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->delete();

        $this->info("Deleted {$deleted} expired onboarding draft(s).");

        return self::SUCCESS;
    }
}
