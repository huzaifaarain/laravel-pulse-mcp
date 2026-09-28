<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services;

use Closure;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\DataTransferObjects\AggregateResult;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Pulse;

/**
 * The single read path into Pulse storage: caps result sizes, caches briefly, and never records itself.
 */
final readonly class PulseRepository
{
    public function __construct(
        private Storage $storage,
        private Pulse $pulse,
        private Cache $cache,
        private Config $config,
    ) {}

    public function limit(?int $limit): int
    {
        $max = max(1, (int) $this->config->get('pulse-mcp.limits.max', 100));

        return max(1, min($limit ?? (int) $this->config->get('pulse-mcp.limits.default', 20), $max));
    }

    /**
     * @param  list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     * @param  (Closure(object): bool)|null  $filter
     */
    public function aggregate(string $type, array $aggregates, Period $period, ?string $orderBy, int $limit, ?Closure $filter = null): AggregateResult
    {
        $fetch = $filter instanceof Closure ? $this->scanLimit() : $limit + 1;

        /** @var list<object> $rows */
        $rows = $this->remember(
            ['aggregate', $type, $aggregates, $period->value, $orderBy, $fetch],
            fn (): array => $this->storage->aggregate($type, $aggregates, $period->interval(), $orderBy, 'desc', $fetch)->values()->all(),
        );

        return $this->slice($rows, $limit, $filter);
    }

    /**
     * @param  list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @param  (Closure(object): bool)|null  $filter
     */
    public function aggregateTypes(array $types, string $aggregate, Period $period, ?string $orderBy, int $limit, ?Closure $filter = null): AggregateResult
    {
        $fetch = $filter instanceof Closure ? $this->scanLimit() : $limit + 1;

        /** @var list<object> $rows */
        $rows = $this->remember(
            ['aggregateTypes', $types, $aggregate, $period->value, $orderBy, $fetch],
            fn (): array => $this->storage->aggregateTypes($types, $aggregate, $period->interval(), $orderBy, 'desc', $fetch)->values()->all(),
        );

        return $this->slice($rows, $limit, $filter);
    }

    /**
     * @param  list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return array<string, float>
     */
    public function aggregateTotal(array $types, string $aggregate, Period $period): array
    {
        return $this->remember(
            ['aggregateTotal', $types, $aggregate, $period->value],
            function () use ($types, $aggregate, $period): array {
                $totals = $this->storage->aggregateTotal($types, $aggregate, $period->interval());

                // Types with no data are missing from the storage result, so default them to zero.
                return array_map(
                    static fn (string $type): float => $totals instanceof Collection ? (float) $totals->get($type, 0) : 0.0,
                    array_combine($types, $types),
                );
            },
        );
    }

    /**
     * @param  list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return array<string, array<string, array<string, int|float|null>>>
     */
    public function graph(array $types, string $aggregate, Period $period): array
    {
        return $this->remember(
            ['graph', $types, $aggregate, $period->value],
            fn (): array => $this->storage->graph($types, $aggregate, $period->interval())->toArray(),
        );
    }

    /**
     * @param  list<string>|null  $keys
     * @return list<object{timestamp: int, key: string, value: string}>
     */
    public function values(string $type, ?array $keys = null): array
    {
        return $this->remember(
            ['values', $type, $keys],
            fn (): array => array_values($this->storage->values($type, $keys)->all()),
        );
    }

    /**
     * @param  list<object>  $rows
     * @param  (Closure(object): bool)|null  $filter
     */
    private function slice(array $rows, int $limit, ?Closure $filter): AggregateResult
    {
        $rows = $filter instanceof Closure ? array_values(array_filter($rows, $filter)) : $rows;

        return new AggregateResult(array_slice($rows, 0, $limit), count($rows) > $limit);
    }

    private function scanLimit(): int
    {
        return max(1, (int) $this->config->get('pulse-mcp.limits.search_scan', 1000));
    }

    /**
     * @template T
     *
     * @param  list<mixed>  $key
     * @param  Closure(): T  $callback
     * @return T
     */
    private function remember(array $key, Closure $callback): mixed
    {
        $query = fn (): mixed => $this->pulse->ignore($callback);
        $ttl = (int) $this->config->get('pulse-mcp.cache_ttl', 10);

        if ($ttl <= 0) {
            return $query();
        }

        return $this->cache->remember('pulse-mcp:'.md5(serialize($key)), $ttl, $query);
    }
}
