<?php

namespace Tests\Feature\Operations;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CronRunTest extends TestCase
{
    public function test_cron_run_returns_403_when_key_is_missing(): void
    {
        config(['cron.key' => 'secret-key']);

        $this->get(route('public.cron.run'))
            ->assertForbidden();
    }

    public function test_cron_run_returns_403_when_key_is_wrong(): void
    {
        config(['cron.key' => 'secret-key']);

        $this->get(route('public.cron.run', ['key' => 'wrong-key']))
            ->assertForbidden();
    }

    public function test_cron_run_executes_schedule_and_returns_200_with_output(): void
    {
        config(['cron.key' => 'secret-key']);
        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('No scheduled commands are ready to run.');

        $this->get(route('public.cron.run', ['key' => 'secret-key']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('No scheduled commands are ready to run.');
    }

    public function test_cron_run_returns_403_when_config_key_is_null(): void
    {
        config(['cron.key' => null]);

        $this->get(route('public.cron.run', ['key' => 'any-key']))
            ->assertForbidden();
    }
}
