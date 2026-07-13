<?php

namespace Tests\Feature\Operations;

use App\Support\QueueKicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The queue-kick endpoint + the fire-and-forget kicker. */
class QueueKickTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_rejects_missing_or_wrong_key(): void
    {
        config(['cron.key' => 'secret-key']);

        $this->get('/cron/queue-kick')->assertForbidden();
        $this->get('/cron/queue-kick?key=wrong')->assertForbidden();
    }

    public function test_endpoint_drains_a_database_queued_job(): void
    {
        config(['cron.key' => 'secret-key']);

        dispatch(function (): void {
            Cache::put('queue-kick-proof', 'delivered', 300);
        })->onConnection('database');

        $this->assertDatabaseCount('jobs', 1);

        $this->get('/cron/queue-kick?key=secret-key')->assertOk();

        $this->assertSame('delivered', Cache::get('queue-kick-proof'));
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_fire_hits_the_endpoint_with_the_key(): void
    {
        config(['cron.key' => 'secret-key']);
        Http::fake();

        QueueKicker::fire();

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/cron/queue-kick')
                && str_contains($request->url(), 'key=secret-key');
        });
    }
}
