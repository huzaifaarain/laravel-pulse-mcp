<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries;

use Carbon\CarbonImmutable;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;

/**
 * Current server snapshot from `system` values plus CPU and memory statistics over the period.
 */
final readonly class SummarizeServers
{
    /**
     * Pulse's own servers card treats a server as offline after 30 seconds.
     */
    private const int STALE_SECONDS = 30;

    public function __construct(private PulseRepository $pulseRepository) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(Period $period, bool $includeSeries = false): array
    {
        $graph = $this->pulseRepository->graph(['cpu', 'memory'], 'avg', $period);
        $now = CarbonImmutable::now()->getTimestamp();
        $servers = [];

        foreach ($this->pulseRepository->values('system') as $value) {
            $system = json_decode($value->value, true);
            $system = is_array($system) ? $system : [];
            $series = $graph[$value->key] ?? [];

            $server = [
                'name' => (string) ($system['name'] ?? $value->key),
                'last_reported_at' => CarbonImmutable::createFromTimestamp($value->timestamp)->toIso8601String(),
                'reporting' => $now - $value->timestamp <= self::STALE_SECONDS,
                'cpu_percent' => isset($system['cpu']) ? (int) $system['cpu'] : null,
                'memory_used_mb' => isset($system['memory_used']) ? (int) $system['memory_used'] : null,
                'memory_total_mb' => isset($system['memory_total']) ? (int) $system['memory_total'] : null,
                'storage' => array_values(array_map(static fn (mixed $disk): array => [
                    'directory' => is_array($disk) ? (string) ($disk['directory'] ?? '') : '',
                    'used_mb' => is_array($disk) ? (int) ($disk['used'] ?? 0) : 0,
                    'total_mb' => is_array($disk) ? (int) ($disk['total'] ?? 0) : 0,
                ], is_array($system['storage'] ?? null) ? $system['storage'] : [])),
                'cpu' => $this->stats($series['cpu'] ?? []),
                'memory_mb' => $this->stats($series['memory'] ?? []),
            ];

            if ($includeSeries) {
                $server['series'] = [
                    'cpu' => array_filter($series['cpu'] ?? [], static fn (mixed $point): bool => $point !== null),
                    'memory_mb' => array_filter($series['memory'] ?? [], static fn (mixed $point): bool => $point !== null),
                ];
            }

            $servers[] = $server;
        }

        return $servers;
    }

    /**
     * @param  array<string, int|float|null>  $points
     * @return array{avg: float|null, peak: float|null}
     */
    private function stats(array $points): array
    {
        $points = array_filter($points, is_numeric(...));

        if ($points === []) {
            return ['avg' => null, 'peak' => null];
        }

        return [
            'avg' => round(array_sum($points) / count($points), 2),
            'peak' => (float) max($points),
        ];
    }
}
