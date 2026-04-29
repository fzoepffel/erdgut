<?php

namespace Tests\Feature\Onboarding;

use Tests\TestCase;

/**
 * W2-08: the progress indicator collapses to four visible segments
 * (Konto · Adresse · Box · Zahlung) regardless of which actual step the
 * customer is on. Tests below render the component directly and assert
 * which segment carries the "current" highlight for each $step value.
 */
class OnboardingProgressIndicatorTest extends TestCase
{
    private function render(string $step, string $intent = 'subscription'): string
    {
        return view('components.onboarding.progress', [
            'step' => $step,
            'intent' => $intent,
        ])->render();
    }

    public function test_renders_exactly_four_visible_segments(): void
    {
        $html = $this->render('account');

        // Four <li> children → four segments.
        $this->assertSame(
            4,
            substr_count($html, '<li class='),
            'Progress indicator should render exactly 4 <li> segments.'
        );
        // The visible labels.
        $this->assertStringContainsString(__('Account'), $html);
        $this->assertStringContainsString(__('Address'), $html);
        $this->assertStringContainsString(__('Box'), $html);
        $this->assertStringContainsString(__('Payment'), $html);
    }

    public function test_account_step_highlights_segment_one(): void
    {
        $html = $this->render('account');
        $this->assertStringContainsString('font-semibold text-brand-ink">'.__('Account'), $html);
    }

    public function test_address_step_highlights_segment_two(): void
    {
        $html = $this->render('address');
        $this->assertStringContainsString('font-semibold text-brand-ink">'.__('Address'), $html);
    }

    /**
     * @dataProvider boxStepProvider
     */
    public function test_internal_steps_collapse_into_box_segment(string $step): void
    {
        $html = $this->render($step);
        $this->assertStringContainsString(
            'font-semibold text-brand-ink">'.__('Box'),
            $html,
            "Step '{$step}' should highlight the visible 'Box' segment."
        );
    }

    public static function boxStepProvider(): array
    {
        return [
            'preferences' => ['preferences'],
            'commitment' => ['commitment'],
            'subscription_schedule' => ['subscription_schedule'],
            'trial_delivery' => ['trial_delivery'],
        ];
    }

    /**
     * @dataProvider paymentStepProvider
     */
    public function test_payment_steps_highlight_segment_four(string $step): void
    {
        $html = $this->render($step);
        $this->assertStringContainsString('font-semibold text-brand-ink">'.__('Payment'), $html);
    }

    public static function paymentStepProvider(): array
    {
        return [
            'subscription_payment' => ['subscription_payment'],
            'trial_payment' => ['trial_payment'],
        ];
    }
}
