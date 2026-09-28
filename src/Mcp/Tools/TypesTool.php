<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\InspectPulseStorage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_types')]
#[Description('List every entry type Pulse has recorded, including types written by third-party or custom cards, with the aggregates available for each. Use the result as input for pulse_aggregate.')]
#[IsReadOnly]
#[IsIdempotent]
class TypesTool extends PulseTool
{
    private const array BUILT_IN = [
        'slow_query', 'slow_request', 'slow_user_request', 'slow_job', 'slow_outgoing_request', 'exception',
        'queued', 'processing', 'processed', 'released', 'failed', 'cache_hit', 'cache_miss',
        'user_request', 'user_job', 'cpu', 'memory', 'system',
    ];

    public function handle(Request $request, InspectPulseStorage $storage): ResponseFactory
    {
        $aggregates = [];

        foreach ($storage->aggregateTypes() as $type => $available) {
            $aggregates[] = ['type' => $type, 'aggregates' => $available, 'built_in' => in_array($type, self::BUILT_IN, true)];
        }

        $values = array_map(
            fn (string $type): array => ['type' => $type, 'built_in' => in_array($type, self::BUILT_IN, true)],
            $storage->valueTypes(),
        );

        return Response::structured([
            'aggregate_types' => $aggregates,
            'value_types' => $values,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'aggregate_types' => $schema->array()->items($schema->object())->description('Types stored as aggregates, readable with pulse_aggregate.')->required(),
            'value_types' => $schema->array()->items($schema->object())->description('Types stored as latest values, readable with pulse_aggregate in values mode.')->required(),
        ];
    }
}
