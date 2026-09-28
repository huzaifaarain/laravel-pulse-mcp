<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use Carbon\CarbonImmutable;
use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\ServersTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pulse\Facades\Pulse;

final class ServersToolFunctionalTest extends TestCase
{
    public function test_it_reports_the_server_snapshot_and_period_statistics(): void
    {
        // Arrange

        $this->travelTo($now = CarbonImmutable::parse('2026-09-28 10:00:00'));

        Pulse::record('cpu', 'web-1', 20, $now->subMinutes(2))->avg()->onlyBuckets();

        Pulse::record('cpu', 'web-1', 60, $now)->avg()->onlyBuckets();

        Pulse::record('memory', 'web-1', 1024, $now)->avg()->onlyBuckets();

        Pulse::set('system', 'web-1', json_encode([
            'name' => 'web-1',
            'cpu' => 60,
            'memory_used' => 1024,
            'memory_total' => 4096,
            'storage' => [['directory' => '/', 'total' => 50000, 'used' => 20000]],
        ]));

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(ServersTool::class, ['period' => '1h']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('period', '1h')
            ->where('servers.0', [
                'name' => 'web-1',
                'last_reported_at' => $now->toIso8601String(),
                'reporting' => true,
                'cpu_percent' => 60,
                'memory_used_mb' => 1024,
                'memory_total_mb' => 4096,
                'storage' => [['directory' => '/', 'used_mb' => 20000, 'total_mb' => 50000]],
                'cpu' => ['avg' => 40, 'peak' => 60],
                'memory_mb' => ['avg' => 1024, 'peak' => 1024],
            ]));
    }

    public function test_it_marks_servers_that_stopped_reporting(): void
    {
        // Arrange

        $this->travelTo(CarbonImmutable::parse('2026-09-28 10:00:00'));

        Pulse::set('system', 'worker-1', json_encode(['name' => 'worker-1']));

        Pulse::ingest();

        $this->travel(5)->minutes();

        // Act

        $testResponse = PulseServer::tool(ServersTool::class);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('servers.0.reporting', false)
            ->etc());
    }
}
