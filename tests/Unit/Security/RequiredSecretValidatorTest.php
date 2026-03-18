<?php

namespace Tests\Unit\Security;

use App\Support\RequiredSecretValidator;
use PHPUnit\Framework\TestCase;

class RequiredSecretValidatorTest extends TestCase
{
    public function test_non_production_environments_do_not_require_secrets(): void
    {
        $validator = new RequiredSecretValidator;

        $missing = $validator->missingForEnvironment('local', [
            'APP_KEY' => '',
            'CRON_KEY' => '',
        ]);

        $this->assertSame([], $missing);
    }

    public function test_staging_requires_app_key_and_non_default_cron_key(): void
    {
        $validator = new RequiredSecretValidator;

        $missing = $validator->missingForEnvironment('staging', [
            'APP_KEY' => '',
            'CRON_KEY' => 'change-this-32-char-secret',
        ]);

        $this->assertSame(['APP_KEY', 'CRON_KEY'], $missing);
    }

    public function test_production_accepts_valid_secret_values(): void
    {
        $validator = new RequiredSecretValidator;

        $missing = $validator->missingForEnvironment('production', [
            'APP_KEY' => 'base64:GQ1nyuEIv4L3pzQbnxEl90zw54kuWnV2v3qDwckEKGA=',
            'CRON_KEY' => '7d3f46ba244a43d5a53da2a6c7176f75',
        ]);

        $this->assertSame([], $missing);
    }
}
