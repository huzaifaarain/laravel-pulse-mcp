<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\OverviewTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pulse\Facades\Pulse;

final class OverviewToolFunctionalTest extends TestCase
{
    public function test_it_summarises_totals_and_top_rows_for_the_period(): void
    {
        // Arrange

        Pulse::record('exception', json_encode(['RuntimeException', 'app/Foo.php:3']), now()->getTimestamp())->max()->count();

        Pulse::record('exception', json_encode(['RuntimeException', 'app/Foo.php:3']), now()->getTimestamp())->max()->count();

        Pulse::record('slow_job', 'App\Jobs\Sync', 2000)->max()->count();

        Pulse::record('failed', 'database:default')->count()->onlyBuckets();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(OverviewTool::class, ['period' => '1h']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('period', '1h')
            ->where('totals', [
                'exception' => 2,
                'slow_query' => 0,
                'slow_request' => 0,
                'slow_job' => 1,
                'slow_outgoing_request' => 0,
                'failed' => 1,
                'cache_hit' => 0,
                'cache_miss' => 0,
            ])
            ->where('top.exception.0.class', 'RuntimeException')
            ->where('top.exception.0.count', 2)
            ->where('top.slow_job.0.job', 'App\Jobs\Sync')
            ->where('top.slow_query', [])
            ->has('warnings'));
    }

    public function test_it_defaults_to_the_last_24_hours(): void
    {
        // Act

        $testResponse = PulseServer::tool(OverviewTool::class);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json->where('period', '24h')->etc());
    }

    public function test_it_rejects_periods_pulse_does_not_aggregate(): void
    {
        // Act

        $testResponse = PulseServer::tool(OverviewTool::class, ['period' => '30m']);

        // Assert

        $testResponse->assertHasErrors();
    }
}
