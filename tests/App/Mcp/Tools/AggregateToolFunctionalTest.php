<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\AggregateTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pulse\Facades\Pulse;

final class AggregateToolFunctionalTest extends TestCase
{
    public function test_it_ranks_a_custom_type_by_several_aggregates(): void
    {
        // Arrange

        Pulse::record('stripe_charge', '["usd","card"]', 500)->sum()->count();

        Pulse::record('stripe_charge', '["usd","card"]', 700)->sum()->count();

        Pulse::record('stripe_charge', '["eur","sepa"]', 100)->sum()->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(AggregateTool::class, [
            'types' => ['stripe_charge'],
            'aggregates' => ['sum', 'count'],
            'period' => '1h',
        ]);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('mode', 'aggregate')
            ->where('rows.0.key_parts', ['usd', 'card'])
            ->where('rows.0.sum', 1200)
            ->where('rows.0.count', 2)
            ->where('rows.1.key_parts', ['eur', 'sepa'])
            ->where('truncated', false)
            ->etc());
    }

    public function test_it_compares_several_types_with_one_aggregate(): void
    {
        // Arrange

        Pulse::record('cache_hit', 'settings', 1)->count();

        Pulse::record('cache_miss', 'settings', 1)->count();

        Pulse::record('cache_miss', 'settings', 1)->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(AggregateTool::class, [
            'types' => ['cache_hit', 'cache_miss'],
            'order_by' => 'cache_miss',
            'period' => '1h',
        ]);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('rows.0.key', 'settings')
            ->where('rows.0.cache_hit', 1)
            ->where('rows.0.cache_miss', 2)
            ->etc());
    }

    public function test_it_returns_series_for_bucket_only_types(): void
    {
        // Arrange

        Pulse::record('webhook_received', 'github')->count()->onlyBuckets();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(AggregateTool::class, ['mode' => 'graph', 'types' => ['webhook_received'], 'period' => '1h']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('mode', 'graph')
            ->where('keys.0.key', 'github')
            ->has('keys.0.series.webhook_received', 1)
            ->etc());
    }

    public function test_it_reads_latest_values_and_decodes_json(): void
    {
        // Arrange

        Pulse::set('deployment', 'current', '{"version":"v1.2.3"}');

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(AggregateTool::class, ['mode' => 'values', 'types' => ['deployment']]);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('values.0.key', 'current')
            ->where('values.0.value', ['version' => 'v1.2.3'])
            ->etc());
    }

    public function test_it_rejects_types_pulse_has_not_recorded(): void
    {
        // Act

        $testResponse = PulseServer::tool(AggregateTool::class, ['types' => ['made_up']]);

        // Assert

        $testResponse->assertHasErrors();
    }

    public function test_it_rejects_several_types_with_several_aggregates(): void
    {
        // Arrange

        Pulse::record('cache_hit', 'a', 1)->count();

        Pulse::record('cache_miss', 'a', 1)->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(AggregateTool::class, [
            'types' => ['cache_hit', 'cache_miss'],
            'aggregates' => ['count', 'sum'],
        ]);

        // Assert

        $testResponse->assertHasErrors(['Use one aggregate when reading several types, or one type when reading several aggregates.']);
    }
}
