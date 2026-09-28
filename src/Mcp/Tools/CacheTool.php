<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\SummarizeCache;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_cache')]
#[Description('Report cache hit and miss totals, overall hit rate, and the busiest cache keys (keys may be redacted) for a period.')]
#[IsReadOnly]
#[IsIdempotent]
class CacheTool extends PulseTool
{
    public function handle(Request $request, SummarizeCache $cache, PulseRepository $repository): ResponseFactory
    {
        $this->validate($request, [
            'sort' => ['nullable', Rule::in(['hits', 'misses'])],
        ]);

        $period = $this->period($request);

        return Response::structured([
            'period' => $period->value,
            ...$cache->execute(
                $period,
                (string) ($request->get('sort') ?? 'hits'),
                $repository->limit($request->integer('limit') ?: null),
                $request->string('search')->toString() ?: null,
            ),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
            'sort' => $schema->string()->enum(['hits', 'misses'])->description('Order keys by hits or misses. Defaults to hits.'),
            'limit' => $this->limitSchema($schema),
            'search' => $this->searchSchema($schema, 'cache keys'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->required(),
            'hits' => $schema->integer()->required(),
            'misses' => $schema->integer()->required(),
            'hit_rate' => $schema->number()->nullable()->description('Hits divided by lookups, null without lookups.')->required(),
            'keys' => $schema->array()->items($schema->object())->required(),
            'truncated' => $schema->boolean()->required(),
        ];
    }
}
