<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\SummarizeQueues;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_queues')]
#[Description('Summarise queue throughput per connection:queue for a period: jobs queued, processing, processed, released and failed. Set include_series for per-minute buckets to spot backlogs or failure spikes.')]
#[IsReadOnly]
#[IsIdempotent]
class QueuesTool extends PulseTool
{
    public function handle(Request $request, SummarizeQueues $queues): ResponseFactory
    {
        $this->validate($request, [
            'queue' => ['nullable', 'string', 'max:255'],
            'include_series' => ['nullable', 'boolean'],
        ]);

        $period = $this->period($request);

        return Response::structured([
            'period' => $period->value,
            'queues' => $queues->execute($period, $request->string('queue')->toString() ?: null, $request->boolean('include_series')),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
            'queue' => $schema->string()->description('Only include connection:queue names containing this text, e.g. "redis:emails".'),
            'include_series' => $schema->boolean()->description('Include non-empty per-bucket counts. Defaults to false.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->required(),
            'queues' => $schema->array()->items($schema->object())->description('One row per connection:queue with totals per job state.')->required(),
        ];
    }
}
