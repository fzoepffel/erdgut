<?php

namespace Tests\Feature\Onboarding;

use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W2-12: the box-size cards on the onboarding preferences step now carry a
 * plant-stage illustration as a visual anchor — the same asset that appears
 * later on /subscriptions/show, so the customer sees their chosen size in
 * continuity from pick → dashboard.
 *
 * The image is decorative (alt=""): the size name + tagline + price already
 * convey the meaning, so screen readers don't need to repeat it.
 */
class BoxSizeCardImageryTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_size_card_renders_its_plant_stage_illustration(): void
    {
        $user = User::factory()->create();
        OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_PREFERENCES,
            'data' => [
                'account_completed' => true,
                'address_completed' => true,
            ],
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.preferences'));
        $response->assertOk();

        $html = $response->getContent();

        foreach (['small', 'medium', 'large'] as $size) {
            // Card markup is keyed by data-testid; isolate the card so the
            // assertion targets the right <img>.
            $pattern = '/data-testid="onboarding-box-size-card-'.$size.'".*?<\/label>/s';
            $this->assertMatchesRegularExpression($pattern, $html, "expected card markup for size '{$size}'");

            preg_match($pattern, $html, $cardMatch);
            $card = $cardMatch[0] ?? '';

            $this->assertStringContainsString(
                'box-plant-stage-'.$size.'-transparent.png',
                $card,
                "expected the {$size} card to render the matching plant-stage asset"
            );

            // Decorative — the radio's accessible name already conveys size + price.
            $this->assertMatchesRegularExpression(
                '/<img[^>]*\balt=""[^>]*>/',
                $card,
                "expected decorative alt=\"\" on the {$size} card image"
            );
        }
    }
}
