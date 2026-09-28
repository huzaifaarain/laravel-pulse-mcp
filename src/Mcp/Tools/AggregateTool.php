<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use Carbon\CarbonImmutable;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\InspectPulseStorage;
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

#[Name('pulse_aggregate')]
#[Description('Read any Pulse type directly, including types recorded by custom or third-party cards. Discover types with pulse_types first. Modes: "aggregate" ranks keys by one or more aggregates (one type) or one aggregate across several types; "graph" returns per-bucket series for bucket-only types such as queues or cpu; "values" returns latest values for value types.')]
#[IsReadOnly]
#[IsIdempotent]
class AggregateTool extends PulseTool
{
    private const array AGGREGATES = ['count', 'min', 'max', 'sum', 'avg'];

    private const array MODES = ['aggregate', 'graph', 'values'];

    public function handle(Request $request, PulseRepository $repository, InspectPulseStorage $storage): Response|ResponseFactory
    {
        $mode = (string) ($request->get('mode') ?? 'aggregate');
        $known = $mode === 'values' ? $storage->valueTypes() : array_keys($storage->aggregateTypes());

        $this->validate($request, [
            'mode' => ['nullable', Rule::in(self::MODES)],
            'types' => ['required', 'array', 'min:1', 'max:10'],
            'types.*' => ['string', Rule::in($known)],
            'aggregates' => ['nullable', 'array', 'min:1'],
            'aggregates.*' => [Rule::in(self::AGGREGATES)],
            'order_by' => ['nullable', 'string'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string'],
        ]);

        /** @var list<string> $types */
        $types = array_values($request->array('types'));

        /** @var list<'count'|'min'|'max'|'sum'|'avg'> $aggregates */
        $aggregates = array_values($request->array('aggregates') ?: ['count']);

        if ($mode === 'aggregate' && count($types) > 1 && count($aggregates) > 1) {
            return Response::error('Use one aggregate when reading several types, or one type when reading several aggregates.');
        }

        $period = $this->period($request);

        return Response::structured(match ($mode) {
            'values' => $this->values($repository, $types[0], $request->array('keys') ?: null),
            'graph' => $this->graph($repository, $types, $aggregates[0], $period),
            default => $this->aggregate($repository, $request, $types, $aggregates, $period),
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()->enum(self::MODES)->description('Defaults to aggregate.'),
            'types' => $schema->array()->items($schema->string())->description('Pulse types from pulse_types. Values mode reads the first type.')->required(),
            'aggregates' => $schema->array()->items($schema->string()->enum(self::AGGREGATES))->description('Aggregates to read. Only those listed by pulse_types exist. Defaults to ["count"].'),
            'order_by' => $schema->string()->description('Aggregate (single type) or type (several types) to order by, descending. Defaults to the first.'),
            'period' => $this->periodSchema($schema),
            'limit' => $this->limitSchema($schema),
            'keys' => $schema->array()->items($schema->string())->description('Values mode only: restrict to these keys.'),
        ];
    }

    /**
     * @param  list<string>  $types
     * @param  list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     * @return array<string, mixed>
     */
    private function aggregate(PulseRepository $repository, Request $request, array $types, array $aggregates, Period $period): array
    {
        $limit = $repository->limit($request->integer('limit') ?: null);
        $orderBy = $request->string('order_by')->toString() ?: null;

        $result = count($types) === 1
            ? $repository->aggregate($types[0], $aggregates, $period, in_array($orderBy, $aggregates, true) ? $orderBy : null, $limit)
            : $repository->aggregateTypes($types, $aggregates[0], $period, in_array($orderBy, $types, true) ? $orderBy : null, $limit);

        return [
            'mode' => 'aggregate',
            'period' => $period->value,
            'rows' => array_map(fn (\stdClass $row): array => [...$this->key($row->key), ...array_diff_key((array) $row, ['key' => true])], $result->rows),
            'truncated' => $result->truncated,
        ];
    }

    /**
     * @param  list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return array<string, mixed>
     */
    private function graph(PulseRepository $repository, array $types, string $aggregate, Period $period): array
    {
        $series = [];

        foreach ($repository->graph($types, $aggregate, $period) as $key => $byType) {
            $series[] = [
                ...$this->key((string) $key),
                'series' => array_map(
                    static fn (array $points): array => array_filter($points, static fn (mixed $point): bool => $point !== null),
                    $byType,
                ),
            ];
        }

        return [
            'mode' => 'graph',
            'period' => $period->value,
            'aggregate' => $aggregate,
            'keys' => $series,
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $keys
     * @return array<string, mixed>
     */
    private function values(PulseRepository $repository, string $type, ?array $keys): array
    {
        $keys = $keys === null ? null : array_values(array_map(static fn (mixed $key): string => (string) $key, $keys));

        return [
            'mode' => 'values',
            'type' => $type,
            'values' => array_map(fn (object $value): array => [
                ...$this->key($value->key),
                'value' => json_validate($value->value) ? json_decode($value->value, true) : $value->value,
                'updated_at' => CarbonImmutable::createFromTimestamp($value->timestamp)->toIso8601String(),
            ], $repository->values($type, $keys)),
        ];
    }

    /**
     * Custom cards often store JSON tuples as keys; expose their parts alongside the raw key.
     *
     * @return array<string, mixed>
     */
    private function key(?string $key): array
    {
        $parts = $key !== null && str_starts_with($key, '[') ? json_decode($key, true) : null;

        return is_array($parts) && array_is_list($parts) ? ['key' => $key, 'key_parts' => $parts] : ['key' => $key];
    }
}
