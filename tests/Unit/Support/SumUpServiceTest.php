<?php

namespace Tests\Unit\Support;

use App\Support\SumUpService;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class SumUpServiceTest extends TestCase
{
    // SumUpService now lazily validates config on first API call, not in the
    // constructor. These tests verify that instantiation succeeds even with
    // missing config, and that validation fires when the service is used.

    public function test_constructor_succeeds_even_when_api_key_missing(): void
    {
        Config::set('sumup.subscription.api_key', '');
        Config::set('sumup.subscription.merchant_code', '');

        // Should not throw — boot() is deferred until first API call
        $service = new SumUpService('subscription');

        $this->assertInstanceOf(SumUpService::class, $service);
    }

    public function test_boot_throws_when_api_key_missing(): void
    {
        Config::set('sumup.subscription.api_key', '');
        Config::set('sumup.subscription.merchant_code', 'MK01A8C2');

        $service = new SumUpService('subscription');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.subscription.api_key');

        // Trigger boot via a public method
        $service->getCheckout('any-id');
    }

    public function test_boot_throws_when_merchant_code_missing(): void
    {
        Config::set('sumup.subscription.api_key', 'sk_test_example');
        Config::set('sumup.subscription.merchant_code', '');

        $service = new SumUpService('subscription');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.subscription.merchant_code');

        $service->getCheckout('any-id');
    }

    public function test_constructor_creates_service_when_required_config_is_present(): void
    {
        Config::set('sumup.subscription.api_key', 'sk_test_example');
        Config::set('sumup.subscription.merchant_code', 'MK01A8C2');

        $service = new SumUpService('subscription');

        $this->assertInstanceOf(SumUpService::class, $service);
    }

    public function test_spargel_profile_reads_spargel_config_keys(): void
    {
        Config::set('sumup.spargel.api_key', 'sk_live_example');
        Config::set('sumup.spargel.merchant_code', 'LIVE_MERCHANT');

        $service = new SumUpService('spargel');

        $this->assertInstanceOf(SumUpService::class, $service);
    }

    public function test_spargel_profile_throws_when_api_key_missing(): void
    {
        Config::set('sumup.spargel.api_key', '');
        Config::set('sumup.spargel.merchant_code', 'LIVE_MERCHANT');

        $service = new SumUpService('spargel');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.spargel.api_key');

        $service->getCheckout('any-id');
    }
}
