<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services;

use Illuminate\Contracts\Config\Repository;

/**
 * Mirrors Pulse's recorder threshold lookup so rows can report the threshold that admitted them.
 */
final readonly class ThresholdResolver
{
    private const int DEFAULT_THRESHOLD = 1_000;

    public function __construct(private Repository $repository) {}

    /**
     * @param  class-string  $recorder
     */
    public function resolve(string $recorder, string $key): int
    {
        $threshold = $this->repository->get("pulse.recorders.{$recorder}.threshold", self::DEFAULT_THRESHOLD);

        if (! is_array($threshold)) {
            return (int) $threshold;
        }

        foreach ($threshold as $pattern => $value) {
            if ($pattern !== 'default' && @preg_match((string) $pattern, $key) === 1) {
                return (int) $value;
            }
        }

        return (int) ($threshold['default'] ?? self::DEFAULT_THRESHOLD);
    }
}
