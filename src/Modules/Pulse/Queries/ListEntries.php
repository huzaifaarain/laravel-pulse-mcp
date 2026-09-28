<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries;

use Carbon\CarbonImmutable;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\KeyDecoder;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\Redactor;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\ThresholdResolver;

/**
 * Lists slow and exception entries as decoded, redacted rows.
 */
final readonly class ListEntries
{
    public function __construct(
        private PulseRepository $pulseRepository,
        private KeyDecoder $keyDecoder,
        private Redactor $redactor,
        private ThresholdResolver $thresholdResolver,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, truncated: bool}
     */
    public function execute(EntryType $type, Period $period, string $sort, int $limit, ?string $search = null): array
    {
        $filter = $search === null || $search === ''
            ? null
            : fn (\stdClass $row): bool => str_contains(
                mb_strtolower(implode(' ', array_filter($this->keyDecoder->decode($type->value, $row->key)))),
                mb_strtolower($search),
            );

        $aggregateResult = $this->pulseRepository->aggregate($type->value, ['max', 'count'], $period, $type->orderBy($sort), $limit, $filter);

        return [
            'rows' => array_map(fn (\stdClass $row): array => $this->format($type, $row), $aggregateResult->rows),
            'truncated' => $aggregateResult->truncated,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function format(EntryType $type, \stdClass $row): array
    {
        $fields = $this->keyDecoder->decode($type->value, $row->key);
        $count = (int) $row->count;

        if ($type === EntryType::Exception) {
            return [
                ...$fields,
                'count' => $count,
                'latest_at' => CarbonImmutable::createFromTimestamp((int) $row->max)->toIso8601String(),
            ];
        }

        $threshold = $this->thresholdResolver->resolve($type->recorder(), (string) ($fields[(string) $type->thresholdField()] ?? ''));

        $fields = match ($type) {
            EntryType::SlowQuery => [...$fields, 'sql' => $this->redactor->sql($fields['sql'])],
            EntryType::SlowOutgoingRequest => [...$fields, 'uri' => $this->redactor->url($fields['uri'])],
            default => $fields,
        };

        return [
            ...$fields,
            'count' => $count,
            'slowest_ms' => (int) $row->max,
            'threshold_ms' => $threshold,
        ];
    }
}
