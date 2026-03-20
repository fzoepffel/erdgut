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
        Config::set('sumup.api_key', '');
        Config::set('sumup.merchant_code', 'MK01A8C2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.api_key');

        new SumUpService;
    }

    public function test_constructor_throws_when_merchant_code_missing(): void
    {
        Config::set('sumup.api_key', 'sk_test_example');
        Config::set('sumup.merchant_code', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SumUp configuration: sumup.merchant_code');

        new SumUpService;
    }

    public function test_constructor_creates_service_when_required_config_is_present(): void
    {
        Config::set('sumup.api_key', 'sk_test_example');
        Config::set('sumup.merchant_code', 'MK01A8C2');

        $service = new SumUpService;

        $this->assertInstanceOf(SumUpService::class, $service);
    }
}
