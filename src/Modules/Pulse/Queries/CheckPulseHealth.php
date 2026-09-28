<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries;

use Carbon\CarbonImmutable;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Reports whether Pulse data is fresh enough to trust, with fixes for common gaps.
 */
final readonly class CheckPulseHealth
{
    /**
     * Pulse's own servers card treats a server as offline after 30 seconds.
     */
    private const int SERVER_STALE_SECONDS = 30;

    private const int ENTRY_STALE_MINUTES = 60;

    public function __construct(
        private InspectPulseStorage $inspectPulseStorage,
        private PulseRepository $pulseRepository,
        private Config $config,
    ) {}

    /**
     * @return array{pulse_enabled: bool, ingest_driver: string, latest_entry_at: string|null, servers: list<array{name: string, last_reported_at: string, reporting: bool}>, warnings: list<string>}
     */
    public function execute(): array
    {
        $now = CarbonImmutable::now();
        $enabled = (bool) $this->config->get('pulse.enabled', true);
        $driver = (string) $this->config->get('pulse.ingest.driver', 'storage');
        $latest = $this->inspectPulseStorage->latestEntryTimestamp();

        $servers = array_map(static function (object $value) use ($now): array {
            $system = json_decode($value->value, true);

            return [
                'name' => is_array($system) && is_string($system['name'] ?? null) ? $system['name'] : $value->key,
                'last_reported_at' => CarbonImmutable::createFromTimestamp($value->timestamp)->toIso8601String(),
                'reporting' => $now->getTimestamp() - $value->timestamp <= self::SERVER_STALE_SECONDS,
            ];
        }, $this->pulseRepository->values('system'));

        $warnings = [];

        if (! $enabled) {
            $warnings[] = 'Pulse recording is disabled (pulse.enabled is false).';
        }

        if ($latest === null) {
            $warnings[] = 'Pulse has no recorded entries yet.';
        } elseif ($now->getTimestamp() - $latest > self::ENTRY_STALE_MINUTES * 60) {
            $warnings[] = 'No Pulse entries in the last hour. Data may be stale.';
        }

        if ($driver === 'redis') {
            $warnings[] = 'Redis ingest is enabled: entries reach storage only while `php artisan pulse:work` is running.';
        }

        if (array_filter($servers, static fn (array $server): bool => $server['reporting']) === []) {
            $warnings[] = 'No server is reporting metrics. Ensure `php artisan pulse:check` is running.';
        }

        return [
            'pulse_enabled' => $enabled,
            'ingest_driver' => $driver,
            'latest_entry_at' => $latest === null ? null : CarbonImmutable::createFromTimestamp($latest)->toIso8601String(),
            'servers' => $servers,
            'warnings' => $warnings,
        ];
    }
}
