<?php

namespace Tests\Feature\Onboarding;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PaymentExpectationsComponentTest extends TestCase
{
    public function test_trial_variant_shows_trial_copy_and_marks_step_one_active(): void
    {
        $html = Blade::render('<x-onboarding.payment-expectations intent="trial" />');

        $this->assertStringContainsString(__('Enter card details'), $html);
        $this->assertStringContainsString(__('Confirm with your bank'), $html);
        $this->assertStringContainsString(__('Your trial box ships Monday'), $html);
        $this->assertStringContainsString('aria-current="step"', $html);
        $this->assertStringContainsString(__('What happens next'), $html);
        $this->assertStringNotContainsString(__('Your subscription is live'), $html);
    }

    public function test_subscription_variant_shows_subscription_copy(): void
    {
        $html = Blade::render('<x-onboarding.payment-expectations intent="subscription" />');

        $this->assertStringContainsString(__('Your subscription is live'), $html);
        $this->assertStringContainsString(__('First delivery the following Monday.'), $html);
        $this->assertStringNotContainsString(__('Your trial box ships Monday'), $html);
    }

    public function test_security_badge_component_renders_three_reassurances(): void
    {
        $html = Blade::render('<x-payment.security-badge />');

        $this->assertStringContainsString(__('Secured by SumUp'), $html);
        $this->assertStringContainsString(__('PCI DSS compliant'), $html);
        $this->assertStringContainsString(__('Your card number is never stored on our servers'), $html);
        $this->assertStringContainsString('role="list"', $html);
    }

    public function test_sumup_widget_includes_processing_overlay_markup(): void
    {
        $html = Blade::render('<x-payment.sumup-widget checkout-id="test-123" return-url="/done" intent="one_time" />');

        $this->assertStringContainsString('data-testid="sumup-processing-overlay"', $html);
        $this->assertStringContainsString(__('Processing your payment'), $html);
        $this->assertStringContainsString(__('Please do not close this window. Your bank may ask for a quick confirmation (SMS, app, or popup).'), $html);
        $this->assertStringContainsString(__('Preparing the secure card form…'), $html);
    }

    public function test_onboarding_layout_preconnects_to_sumup_gateway(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/onboarding.blade.php'));

        $this->assertStringContainsString('rel="preconnect" href="https://gateway.sumup.com"', $layout);
        $this->assertStringContainsString('rel="dns-prefetch" href="https://api.sumup.com"', $layout);
    }
}
