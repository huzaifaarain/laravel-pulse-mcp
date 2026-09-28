<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use Carbon\CarbonImmutable;
use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\HealthTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pulse\Facades\Pulse;

final class HealthToolFunctionalTest extends TestCase
{
    public function test_it_reports_fresh_data_without_warnings(): void
    {
        // Arrange

        $this->travelTo($now = CarbonImmutable::parse('2026-09-28 10:00:00'));

        Pulse::record('slow_job', 'App\Jobs\Sync', 2000)->max()->count();

        Pulse::set('system', 'web-1', json_encode(['name' => 'web-1']));

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(HealthTool::class);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('pulse_enabled', true)
            ->where('ingest_driver', 'storage')
            ->where('latest_entry_at', $now->toIso8601String())
            ->where('servers', [['name' => 'web-1', 'last_reported_at' => $now->toIso8601String(), 'reporting' => true]])
            ->where('warnings', []));
    }

    public function test_it_warns_about_missing_entries_and_silent_servers(): void
    {
        // Act

        $testResponse = PulseServer::tool(HealthTool::class);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('latest_entry_at', null)
            ->where('warnings', [
                'Pulse has no recorded entries yet.',
                'No server is reporting metrics. Ensure `php artisan pulse:check` is running.',
            ])
            ->etc());
    }

    public function test_it_warns_when_redis_ingest_needs_the_worker(): void
    {
        // Arrange

        config(['pulse.ingest.driver' => 'redis']);

        // Act

        $testResponse = PulseServer::tool(HealthTool::class);

        // Assert

        $testResponse->assertOk()->assertSee('pulse:work');
    }
}
