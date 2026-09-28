<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;

/**
 * Totals queue throughput per connection:queue, optionally with the per-bucket series.
 */
final readonly class SummarizeQueues
{
    public const array TYPES = ['queued', 'processing', 'processed', 'released', 'failed'];

    public function __construct(private PulseRepository $pulseRepository) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(Period $period, ?string $queue = null, bool $includeSeries = false): array
    {
        $queues = [];

        foreach ($this->pulseRepository->graph(self::TYPES, 'count', $period) as $key => $types) {
            if ($queue !== null && $queue !== '' && ! str_contains((string) $key, $queue)) {
                continue;
            }

            $summary = ['queue' => (string) $key];

            foreach (self::TYPES as $type) {
                $summary[$type] = (int) array_sum(array_filter($types[$type] ?? [], is_numeric(...)));
            }

            if ($includeSeries) {
                $summary['series'] = $this->series($types);
            }

            $queues[] = $summary;
        }

        return $queues;
    }

    /**
     * @param  array<string, array<string, int|float|null>>  $types
     * @return list<array<string, int|string>>
     */
    private function series(array $types): array
    {
        $buckets = [];

        foreach (self::TYPES as $type) {
            foreach ($types[$type] ?? [] as $at => $value) {
                if ($value !== null) {
                    $buckets[$at][$type] = (int) $value;
                }
            }
        }

        ksort($buckets);

        $series = [];

        foreach ($buckets as $at => $counts) {
            $series[] = ['at' => (string) $at, ...$counts];
        }

        return $series;
    }
}
