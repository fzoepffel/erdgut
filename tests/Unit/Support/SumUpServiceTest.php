<?php

namespace Tests\Unit\Support;

use App\Support\SumUpService;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class SumUpServiceTest extends TestCase
{
    public function test_constructor_throws_when_api_key_missing(): void
    {
        Config::set('sumup.subscription.api_key', '');
        Config::set('sumup.subscription.merchant_code', 'MK01A8C2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.subscription.api_key');

        new SumUpService('subscription');
    }

    public function test_constructor_throws_when_merchant_code_missing(): void
    {
        Config::set('sumup.subscription.api_key', 'sk_test_example');
        Config::set('sumup.subscription.merchant_code', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.subscription.merchant_code');

        new SumUpService('subscription');
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.spargel.api_key');

        new SumUpService('spargel');
    }
}
