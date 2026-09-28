<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Laravel\Pulse\Pulse;

/**
 * Reads storage metadata the Pulse storage contract does not expose: freshness and recorded types.
 */
final readonly class InspectPulseStorage
{
    public function __construct(
        private DatabaseManager $databaseManager,
        private Config $config,
        private Pulse $pulse,
    ) {}

    public function latestEntryTimestamp(): ?int
    {
        return $this->pulse->ignore(function (): ?int {
            $latest = $this->connection()->table('pulse_entries')->max('timestamp');

            return $latest === null ? null : (int) $latest;
        });
    }

    /**
     * @return array<string, list<string>>
     */
    public function aggregateTypes(): array
    {
        $rows = $this->pulse->ignore(fn () => $this->connection()
            ->table('pulse_aggregates')
            ->select(['type', 'aggregate'])
            ->distinct()
            ->orderBy('type')
            ->orderBy('aggregate')
            ->get());

        $types = [];

        foreach ($rows as $row) {
            $types[(string) $row->type][] = (string) $row->aggregate;
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    public function valueTypes(): array
    {
        $types = $this->pulse->ignore(fn () => $this->connection()
            ->table('pulse_values')
            ->distinct()
            ->orderBy('type')
            ->pluck('type'));

        return array_values(array_map(static fn (mixed $type): string => (string) $type, $types->all()));
    }

    private function connection(): Connection
    {
        return $this->databaseManager->connection($this->config->get('pulse.storage.database.connection'));
    }
}
