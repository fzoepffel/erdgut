<?php

namespace Tests\Feature\Operations;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthcheckTest extends TestCase
{
    public function test_healthcheck_returns_ok_with_minimal_diagnostics(): void
    {
        $this->get(route('public.health'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checks' => ['app'],
                'timestamp',
            ])
            ->assertJsonMissing(['environment' => app()->environment()])
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'app' => 'ok',
                ],
            ]);
    }

    public function test_healthcheck_includes_database_check_when_debug_enabled(): void
    {
        config(['app.debug' => true]);

        $this->get(route('public.health'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checks' => ['app', 'database'],
                'timestamp',
            ]);
    }

    public function test_healthcheck_returns_service_unavailable_when_database_is_not_reachable(): void
    {
        config(['app.debug' => true]);

        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new \RuntimeException('Database is down'));

        $this->get(route('public.health'))
            ->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => [
                    'app' => 'ok',
                    'database' => 'error',
                ],
            ]);
    }
}
