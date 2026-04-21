<?php

namespace App\Services\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reads/writes the server-side onboarding draft used by the wizard.
 *
 * The draft belongs to the authenticated user (once they complete step A)
 * or to an anonymous session while a guest is mid-wizard.
 */
class OnboardingDraftManager
{
    public const STEP_ACCOUNT = 'account';

    public const STEP_ADDRESS = 'address';

    public const STEP_PREFERENCES = 'preferences';

    public const STEP_COMMITMENT = 'commitment';

    public const STEP_TRIAL_DELIVERY = 'trial_delivery';

    public const STEP_TRIAL_PAYMENT = 'trial_payment';

    public const STEP_SUBSCRIPTION_SCHEDULE = 'subscription_schedule';

    public const STEP_SUBSCRIPTION_PAYMENT = 'subscription_payment';

    public const STEP_COMPLETE = 'complete';

    public const INTENT_UNDECIDED = 'undecided';

    public const INTENT_TRIAL = 'trial';

    public const INTENT_SUBSCRIPTION = 'subscription';

    public const DRAFT_TTL_DAYS = 7;

    /**
     * Return the draft for the current user/session (create if missing).
     */
    public function currentOrCreate(Request $request, ?string $intent = null): OnboardingDraft
    {
        $user = $request->user();

        $draft = $this->findCurrent($request);

        if ($draft === null) {
            $draft = OnboardingDraft::create([
                'user_id' => $user?->id,
                'session_id' => $this->sessionKey($request),
                'intent' => $intent ?? self::INTENT_UNDECIDED,
                'step' => self::STEP_ACCOUNT,
                'data' => [],
                'expires_at' => Carbon::now()->addDays(self::DRAFT_TTL_DAYS),
            ]);
        }

        if ($intent !== null && $intent !== self::INTENT_UNDECIDED && $draft->intent !== $intent) {
            $draft->update(['intent' => $intent]);
        }

        if ($user !== null && $draft->user_id === null) {
            $draft->update(['user_id' => $user->id]);
        }

        return $draft;
    }

    public function findCurrent(Request $request): ?OnboardingDraft
    {
        $user = $request->user();

        if ($user !== null) {
            $userDraft = OnboardingDraft::query()
                ->where('user_id', $user->id)
                ->latest('updated_at')
                ->first();

            if ($userDraft !== null) {
                return $userDraft;
            }
        }

        $sessionKey = $this->sessionKey($request);

        return OnboardingDraft::query()
            ->whereNull('user_id')
            ->where('session_id', $sessionKey)
            ->latest('updated_at')
            ->first();
    }

    /**
     * Merge new payload into the draft's data and optionally advance step.
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(OnboardingDraft $draft, array $payload, ?string $step = null): OnboardingDraft
    {
        $current = $draft->data ?? [];
        $data = array_replace($current, $payload);

        $attributes = ['data' => $data];

        if ($step !== null) {
            $attributes['step'] = $step;
        }

        $attributes['expires_at'] = Carbon::now()->addDays(self::DRAFT_TTL_DAYS);

        $draft->update($attributes);

        return $draft->fresh();
    }

    public function setIntent(OnboardingDraft $draft, string $intent): OnboardingDraft
    {
        $draft->update(['intent' => $intent]);

        return $draft->fresh();
    }

    /**
     * Decide which step to display for a draft based on what data it already has.
     */
    public function nextStep(OnboardingDraft $draft, ?User $user): string
    {
        $data = $draft->data ?? [];

        if ($user === null && empty($data['account_completed'])) {
            return self::STEP_ACCOUNT;
        }

        if (empty($data['address_id']) && empty($data['address_completed'])) {
            return self::STEP_ADDRESS;
        }

        if (empty($data['preferences_completed'])) {
            return self::STEP_PREFERENCES;
        }

        if (! in_array($draft->intent, [self::INTENT_TRIAL, self::INTENT_SUBSCRIPTION], true)) {
            return self::STEP_COMMITMENT;
        }

        if ($draft->intent === self::INTENT_TRIAL) {
            if (empty($data['trial_delivery_completed'])) {
                return self::STEP_TRIAL_DELIVERY;
            }

            return self::STEP_TRIAL_PAYMENT;
        }

        if (empty($data['subscription_schedule_completed'])) {
            return self::STEP_SUBSCRIPTION_SCHEDULE;
        }

        return self::STEP_SUBSCRIPTION_PAYMENT;
    }

    public function clear(OnboardingDraft $draft): void
    {
        $draft->delete();
    }

    private function sessionKey(Request $request): string
    {
        $session = $request->session();
        $key = $session->get('onboarding_session_key');

        if (! is_string($key) || $key === '') {
            $key = (string) Str::uuid();
            $session->put('onboarding_session_key', $key);
        }

        return $key;
    }
}
