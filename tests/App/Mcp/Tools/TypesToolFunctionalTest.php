<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\TypesTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Laravel\Pulse\Facades\Pulse;

final class TypesToolFunctionalTest extends TestCase
{
    public function test_it_lists_built_in_and_custom_types_with_their_aggregates(): void
    {
        // Arrange

        Pulse::record('slow_job', 'App\Jobs\Sync', 2000)->max()->count();

        Pulse::record('stripe_charge', 'usd', 900)->sum();

        Pulse::set('deployment', 'current', 'v1.2.3');

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(TypesTool::class);

        // Assert

        $testResponse->assertOk()->assertStructuredContent([
            'aggregate_types' => [
                ['type' => 'slow_job', 'aggregates' => ['count', 'max'], 'built_in' => true],
                ['type' => 'stripe_charge', 'aggregates' => ['sum'], 'built_in' => false],
            ],
            'value_types' => [
                ['type' => 'deployment', 'built_in' => false],
            ],
        ]);
    }
}
