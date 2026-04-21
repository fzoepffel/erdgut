<?php

namespace Tests\Unit\Services\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OnboardingDraftManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_or_create_creates_fresh_draft_with_intent(): void
    {
        $manager = app(OnboardingDraftManager::class);
        $request = Request::create('/onboard');
        $request->setLaravelSession($this->app['session']->driver('array'));

        $draft = $manager->currentOrCreate($request, OnboardingDraftManager::INTENT_TRIAL);

        $this->assertInstanceOf(OnboardingDraft::class, $draft);
        $this->assertSame(OnboardingDraftManager::INTENT_TRIAL, $draft->intent);
        $this->assertSame(OnboardingDraftManager::STEP_ACCOUNT, $draft->step);
    }

    public function test_update_merges_payload_and_persists_data(): void
    {
        $manager = app(OnboardingDraftManager::class);
        $draft = OnboardingDraft::create([
            'intent' => OnboardingDraftManager::INTENT_UNDECIDED,
            'step' => OnboardingDraftManager::STEP_ACCOUNT,
            'data' => ['box_size' => 'small'],
            'expires_at' => now()->addDays(7),
        ]);

        $updated = $manager->update($draft, ['box_size' => 'medium', 'preferences_completed' => true]);

        $this->assertSame('medium', $updated->data['box_size']);
        $this->assertTrue($updated->data['preferences_completed']);
    }

    public function test_next_step_routes_trial_intent_through_trial_delivery_and_payment(): void
    {
        $manager = app(OnboardingDraftManager::class);
        $user = User::factory()->create();

        $draft = OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => OnboardingDraftManager::INTENT_TRIAL,
            'step' => OnboardingDraftManager::STEP_ACCOUNT,
            'data' => [
                'account_completed' => true,
                'address_id' => 1,
                'address_completed' => true,
                'preferences_completed' => true,
            ],
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertSame(OnboardingDraftManager::STEP_TRIAL_DELIVERY, $manager->nextStep($draft, $user));

        $draft->data = array_merge($draft->data, ['trial_delivery_completed' => true]);
        $draft->save();

        $this->assertSame(OnboardingDraftManager::STEP_TRIAL_PAYMENT, $manager->nextStep($draft->fresh(), $user));
    }

    public function test_next_step_routes_subscription_intent_through_schedule_and_payment(): void
    {
        $manager = app(OnboardingDraftManager::class);
        $user = User::factory()->create();

        $draft = OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_ACCOUNT,
            'data' => [
                'account_completed' => true,
                'address_id' => 1,
                'address_completed' => true,
                'preferences_completed' => true,
            ],
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertSame(OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE, $manager->nextStep($draft, $user));

        $draft->data = array_merge($draft->data, ['subscription_schedule_completed' => true]);
        $draft->save();

        $this->assertSame(OnboardingDraftManager::STEP_SUBSCRIPTION_PAYMENT, $manager->nextStep($draft->fresh(), $user));
    }
}
