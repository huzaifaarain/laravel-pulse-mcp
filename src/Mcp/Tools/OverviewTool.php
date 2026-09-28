<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\CheckPulseHealth;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\ListEntries;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_overview')]
#[Description('Summarise production health for a period: totals of exceptions, slow queries, slow requests, slow jobs, slow outgoing requests, failed jobs and cache hits/misses, plus the top 5 rows of each and freshness warnings. Start investigations here, then drill down with the specific tools.')]
#[IsReadOnly]
#[IsIdempotent]
class OverviewTool extends PulseTool
{
    private const int TOP = 5;

    public function handle(Request $request, PulseRepository $repository, ListEntries $entries, CheckPulseHealth $health): ResponseFactory
    {
        $this->validate($request);

        $period = $this->period($request);

        $totals = $repository->aggregateTotal(
            ['exception', 'slow_query', 'slow_request', 'slow_job', 'slow_outgoing_request', 'failed', 'cache_hit', 'cache_miss'],
            'count',
            $period,
        );

        $top = [];

        foreach (EntryType::cases() as $type) {
            $top[$type->value] = $entries->execute($type, $period, $type->sorts()[0], self::TOP)['rows'];
        }

        return Response::structured([
            'period' => $period->value,
            'totals' => array_map(static fn (float $total): int => (int) $total, $totals),
            'top' => $top,
            'warnings' => $health->execute()['warnings'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->required(),
            'totals' => $schema->object()->description('Occurrence counts per Pulse type in the period. Slow entries only count occurrences above their threshold, and sampling may apply.')->required(),
            'top' => $schema->object()->description('Top rows per type: exceptions by count, slow entries by slowest duration.')->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
