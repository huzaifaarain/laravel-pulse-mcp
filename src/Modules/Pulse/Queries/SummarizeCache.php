<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\Redactor;

/**
 * Cache hit/miss totals and the busiest keys, with keys redacted.
 */
final readonly class SummarizeCache
{
    public function __construct(
        private PulseRepository $pulseRepository,
        private Redactor $redactor,
    ) {}

    /**
     * @return array{hits: int, misses: int, hit_rate: float|null, keys: list<array<string, mixed>>, truncated: bool}
     */
    public function execute(Period $period, string $sort, int $limit, ?string $search = null): array
    {
        $totals = $this->pulseRepository->aggregateTotal(['cache_hit', 'cache_miss'], 'count', $period);

        $filter = $search === null || $search === ''
            ? null
            : static fn (\stdClass $row): bool => str_contains(mb_strtolower((string) $row->key), mb_strtolower($search));

        $result = $this->pulseRepository->aggregateTypes(
            ['cache_hit', 'cache_miss'],
            'count',
            $period,
            $sort === 'misses' ? 'cache_miss' : 'cache_hit',
            $limit,
            $filter,
        );

        return [
            'hits' => (int) $totals['cache_hit'],
            'misses' => (int) $totals['cache_miss'],
            'hit_rate' => $this->rate($totals['cache_hit'], $totals['cache_miss']),
            'keys' => array_map(fn (\stdClass $row): array => [
                'key' => $this->redactor->cacheKey($row->key),
                'hits' => (int) ($row->cache_hit ?? 0),
                'misses' => (int) ($row->cache_miss ?? 0),
                'hit_rate' => $this->rate((float) ($row->cache_hit ?? 0), (float) ($row->cache_miss ?? 0)),
            ], $result->rows),
            'truncated' => $result->truncated,
        ];
    }

    private function rate(float $hits, float $misses): ?float
    {
        $total = $hits + $misses;

        return $total > 0 ? round($hits / $total, 4) : null;
    }
}
