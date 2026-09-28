<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\QueuesTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Pulse\Facades\Pulse;

final class QueuesToolFunctionalTest extends TestCase
{
    public function test_it_totals_each_job_state_per_queue(): void
    {
        // Arrange

        $this->recordQueue('redis:default', ['queued' => 3, 'processed' => 2, 'failed' => 1]);

        $this->recordQueue('redis:emails', ['queued' => 1]);

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(QueuesTool::class, ['period' => '1h']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent([
            'period' => '1h',
            'queues' => [
                ['queue' => 'redis:default', 'queued' => 3, 'processing' => 0, 'processed' => 2, 'released' => 0, 'failed' => 1],
                ['queue' => 'redis:emails', 'queued' => 1, 'processing' => 0, 'processed' => 0, 'released' => 0, 'failed' => 0],
            ],
        ]);
    }

    public function test_it_filters_queues_and_includes_the_series(): void
    {
        // Arrange

        $this->recordQueue('redis:default', ['queued' => 2]);

        $this->recordQueue('redis:emails', ['failed' => 1]);

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(QueuesTool::class, ['period' => '1h', 'queue' => 'emails', 'include_series' => true]);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('queues', 1)
            ->where('queues.0.queue', 'redis:emails')
            ->where('queues.0.series.0.failed', 1)
            ->etc());
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function recordQueue(string $queue, array $counts): void
    {
        foreach ($counts as $type => $count) {
            foreach (range(1, $count) as $ignored) {
                Pulse::record($type, $queue)->count()->onlyBuckets();
            }
        }
    }
}
