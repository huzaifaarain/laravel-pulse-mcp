<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\Redactor;
use Illuminate\Support\Collection;
use Laravel\Pulse\Pulse;

/**
 * Top users by requests, slow requests, or dispatched jobs, resolved through Pulse's user resolver.
 */
final readonly class ListUsage
{
    public const array TYPES = [
        'requests' => 'user_request',
        'slow_requests' => 'slow_user_request',
        'jobs' => 'user_job',
    ];

    public function __construct(
        private PulseRepository $pulseRepository,
        private Redactor $redactor,
        private Pulse $pulse,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, truncated: bool}
     */
    public function execute(string $type, Period $period, int $limit): array
    {
        $result = $this->pulseRepository->aggregate(self::TYPES[$type] ?? self::TYPES['requests'], ['count'], $period, 'count', $limit);

        $resolvesUsers = $this->pulse->resolveUsers(new Collection(array_map(static fn (\stdClass $row): string => (string) $row->key, $result->rows)));

        return [
            'rows' => array_map(function (\stdClass $row) use ($resolvesUsers): array {
                $user = $resolvesUsers->find($row->key);

                return array_filter([
                    'user_id' => (string) $row->key,
                    'name' => $user->name ?? null,
                    'email' => $this->redactor->includesUserEmail() && ($user->extra ?? '') !== '' ? $user->extra : null,
                    'count' => (int) $row->count,
                ], static fn (mixed $value): bool => $value !== null);
            }, $result->rows),
            'truncated' => $result->truncated,
        ];
    }
}
