<?php

namespace Tests\Unit\Logging;

use Tests\TestCase;

class LoggingConfigTest extends TestCase
{
    public function test_ionos_channel_is_configured_with_rotating_file_targets(): void
    {
        $ionos = config('logging.channels.ionos');

        $this->assertIsArray($ionos);
        $this->assertSame('stack', $ionos['driver']);
        $this->assertContains('daily', $ionos['channels']);
        $this->assertContains('critical_daily', $ionos['channels']);
    }

    public function test_daily_channels_have_shared_hosting_friendly_retention_defaults(): void
    {
        $this->assertSame(30, config('logging.channels.daily.days'));
        $this->assertSame(60, config('logging.channels.critical_daily.days'));
        $this->assertSame(env('LOG_LEVEL', 'info'), config('logging.channels.daily.level'));
        $this->assertSame(env('LOG_CRITICAL_LEVEL', 'warning'), config('logging.channels.critical_daily.level'));
    }
}
