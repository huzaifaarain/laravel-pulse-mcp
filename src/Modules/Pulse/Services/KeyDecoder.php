<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services;

/**
 * Pulse stores composite keys as JSON tuples; this turns them into named fields.
 */
final class KeyDecoder
{
    /**
     * @return array<string, string|null>
     */
    public function decode(string $type, ?string $key): array
    {
        $parts = $this->tuple($key);

        return match ($type) {
            'slow_query' => ['sql' => $parts[0] ?? $key, 'location' => $parts[1] ?? null],
            'exception' => ['class' => $parts[0] ?? $key, 'location' => $parts[1] ?? null],
            'slow_request' => ['method' => $parts[0] ?? null, 'route' => $parts[1] ?? $key, 'action' => $parts[2] ?? null],
            'slow_outgoing_request' => ['method' => $parts[0] ?? null, 'uri' => $parts[1] ?? $key],
            'slow_job' => ['job' => $key],
            default => ['key' => $key],
        };
    }

    /**
     * @return list<string|null>
     */
    private function tuple(?string $key): array
    {
        if ($key === null || ! str_starts_with($key, '[')) {
            return [];
        }

        $decoded = json_decode($key, true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return [];
        }

        return array_map(static fn (mixed $part): ?string => is_scalar($part) ? (string) $part : null, $decoded);
    }
}
