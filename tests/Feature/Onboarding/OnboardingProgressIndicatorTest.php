<?php

namespace Tests\Feature\Onboarding;

use Tests\TestCase;

/**
 * The customer-facing progress rail shows four segments — Adresse · Box ·
 * Zeitplan · Zahlung — with no Konto/account segment (that belonged to a
 * separate pre-flow). The account step therefore highlights nothing; each
 * later internal step collapses into one of the four visible segments. The
 * trial path swaps "Zeitplan" for "Lieferung" on segment three.
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

    /**
     * Return the rendered <li> chunk that contains the given label, so a test
     * can assert that segment's state (current / done) in isolation.
     */
    private function segmentFor(string $html, string $label): string
    {
        $chunks = explode('<li', $html);
        foreach ($chunks as $chunk) {
            if (str_contains($chunk, '>'.$label) || str_contains($chunk, $label.'</') || preg_match('/\s'.preg_quote($label, '/').'\s/', $chunk)) {
                return $chunk;
            }
        }

        return '';
    }

    public function test_renders_exactly_four_visible_segments(): void
    {
        $html = $this->render('address');

        // Four <li> children → four segments (subtract 1 for the text before the first tag).
        $this->assertSame(
            4,
            substr_count($html, '<li'),
            'Progress rail should render exactly 4 <li> segments.'
        );

        $this->assertStringContainsString(__('onboarding.step.address'), $html);
        $this->assertStringContainsString(__('onboarding.step.box'), $html);
        $this->assertStringContainsString(__('onboarding.step.schedule'), $html);
        $this->assertStringContainsString(__('onboarding.step.payment'), $html);

        // Konto/account must not appear as a segment.
        $this->assertStringNotContainsString(__('Account'), $html);
        $this->assertStringNotContainsString(__('Konto'), $html);
    }

    public function test_account_step_highlights_nothing(): void
    {
        $html = $this->render('account');

        $this->assertStringNotContainsString('aria-current="step"', $html);
        $this->assertStringNotContainsString(__('onboarding.stepper.now'), $html);
        $this->assertStringNotContainsString(__('onboarding.stepper.done'), $html);
    }

    public function test_address_step_highlights_first_segment(): void
    {
        $html = $this->render('address');

        $segment = $this->segmentFor($html, __('onboarding.step.address'));
        $this->assertStringContainsString('aria-current="step"', $segment);
        $this->assertStringContainsString(__('onboarding.stepper.now'), $segment);
    }

    /**
     * @dataProvider boxStepProvider
     */
    public function test_internal_steps_collapse_into_box_segment(string $step): void
    {
        $html = $this->render($step);

        $segment = $this->segmentFor($html, __('onboarding.step.box'));
        $this->assertStringContainsString(
            'aria-current="step"',
            $segment,
            "Step '{$step}' should highlight the visible 'Box' segment."
        );
        $this->assertStringContainsString(__('onboarding.stepper.now'), $segment);
    }

    public static function boxStepProvider(): array
    {
        return [
            'preferences' => ['preferences'],
            'commitment' => ['commitment'],
        ];
    }

    /**
     * @dataProvider scheduleStepProvider
     */
    public function test_schedule_steps_highlight_third_segment(string $step): void
    {
        $html = $this->render($step);

        $segment = $this->segmentFor($html, __('onboarding.step.schedule'));
        $this->assertStringContainsString('aria-current="step"', $segment);
        $this->assertStringContainsString(__('onboarding.stepper.now'), $segment);
    }

    public static function scheduleStepProvider(): array
    {
        return [
            'subscription_schedule' => ['subscription_schedule'],
        ];
    }

    /**
     * @dataProvider paymentStepProvider
     */
    public function test_payment_steps_highlight_fourth_segment(string $step): void
    {
        $html = $this->render($step);

        $segment = $this->segmentFor($html, __('onboarding.step.payment'));
        $this->assertStringContainsString('aria-current="step"', $segment);
        $this->assertStringContainsString(__('onboarding.stepper.now'), $segment);
    }

    public static function paymentStepProvider(): array
    {
        return [
            'subscription_payment' => ['subscription_payment'],
            'trial_payment' => ['trial_payment'],
        ];
    }

    public function test_trial_third_segment_label_is_delivery(): void
    {
        $html = $this->render('trial_delivery', 'trial');

        $this->assertStringContainsString(__('onboarding.step.delivery'), $html);
        $segment = $this->segmentFor($html, __('onboarding.step.delivery'));
        $this->assertStringContainsString('aria-current="step"', $segment);
    }
}
