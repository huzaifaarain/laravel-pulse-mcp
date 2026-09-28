<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\CacheTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Laravel\Pulse\Facades\Pulse;

final class CacheToolFunctionalTest extends TestCase
{
    public function test_it_reports_totals_hit_rate_and_redacted_keys(): void
    {
        // Arrange

        config(['pulse-mcp.redact.cache_key_patterns' => ['/user:\d+/']]);

        Pulse::record('cache_hit', 'settings', 1)->count();

        Pulse::record('cache_hit', 'settings', 1)->count();

        Pulse::record('cache_hit', 'settings', 1)->count();

        Pulse::record('cache_miss', 'profile:user:7', 1)->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(CacheTool::class, ['period' => '1h']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent([
            'period' => '1h',
            'hits' => 3,
            'misses' => 1,
            'hit_rate' => 0.75,
            'keys' => [
                ['key' => 'settings', 'hits' => 3, 'misses' => 0, 'hit_rate' => 1],
                ['key' => 'profile:[redacted]', 'hits' => 0, 'misses' => 1, 'hit_rate' => 0],
            ],
            'truncated' => false,
        ]);
    }

    public function test_it_orders_keys_by_misses(): void
    {
        // Arrange

        Pulse::record('cache_hit', 'settings', 1)->count();

        Pulse::record('cache_miss', 'feature-flags', 1)->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(CacheTool::class, ['period' => '1h', 'sort' => 'misses', 'limit' => 1]);

        // Assert

        $testResponse->assertOk()->assertSee('feature-flags')->assertDontSee('"key":"settings"');
    }

    public function test_it_reports_no_hit_rate_without_lookups(): void
    {
        // Act

        $testResponse = PulseServer::tool(CacheTool::class);

        // Assert

        $testResponse->assertOk()->assertSee('"hit_rate":null');
    }
}
