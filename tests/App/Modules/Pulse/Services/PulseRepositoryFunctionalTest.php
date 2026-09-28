<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Modules\Pulse\Services;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Laravel\Pulse\Facades\Pulse;

final class PulseRepositoryFunctionalTest extends TestCase
{
    public function test_it_returns_aggregates_ordered_by_the_first_aggregate(): void
    {
        // Arrange

        Pulse::record('slow_job', 'App\Jobs\Fast', 1200)->max()->count();

        Pulse::record('slow_job', 'App\Jobs\Slow', 9000)->max()->count();

        Pulse::ingest();

        // Act

        $aggregateResult = resolve(PulseRepository::class)->aggregate('slow_job', ['max', 'count'], Period::OneHour, null, 10);

        // Assert

        $this->assertSame(['App\Jobs\Slow', 'App\Jobs\Fast'], array_column($aggregateResult->rows, 'key'));

        $this->assertFalse($aggregateResult->truncated);
    }

    public function test_it_flags_truncated_results(): void
    {
        // Arrange

        Pulse::record('slow_job', 'A', 1000)->max()->count();

        Pulse::record('slow_job', 'B', 2000)->max()->count();

        Pulse::ingest();

        // Act

        $aggregateResult = resolve(PulseRepository::class)->aggregate('slow_job', ['max', 'count'], Period::OneHour, null, 1);

        // Assert

        $this->assertSame(['B'], array_column($aggregateResult->rows, 'key'));

        $this->assertTrue($aggregateResult->truncated);
    }

    public function test_it_filters_rows_before_limiting(): void
    {
        // Arrange

        Pulse::record('slow_job', 'App\Jobs\Invoice', 5000)->max()->count();

        Pulse::record('slow_job', 'App\Jobs\Report', 9000)->max()->count();

        Pulse::ingest();

        // Act

        $aggregateResult = resolve(PulseRepository::class)->aggregate(
            'slow_job',
            ['max', 'count'],
            Period::OneHour,
            null,
            1,
            static fn (\stdClass $row): bool => str_contains((string) $row->key, 'Invoice'),
        );

        // Assert

        $this->assertSame(['App\Jobs\Invoice'], array_column($aggregateResult->rows, 'key'));

        $this->assertFalse($aggregateResult->truncated);
    }

    public function test_it_defaults_missing_totals_to_zero(): void
    {
        // Arrange

        Pulse::record('cache_hit', 'users', 1)->count();

        Pulse::ingest();

        // Act

        $totals = resolve(PulseRepository::class)->aggregateTotal(['cache_hit', 'cache_miss'], 'count', Period::OneHour);

        // Assert

        $this->assertSame(['cache_hit' => 1.0, 'cache_miss' => 0.0], $totals);
    }

    public function test_it_reads_latest_values(): void
    {
        // Arrange

        Pulse::set('system', 'web-1', '{"name":"web-1"}');

        Pulse::ingest();

        // Act

        $values = resolve(PulseRepository::class)->values('system');

        // Assert

        $this->assertSame(['web-1'], array_column($values, 'key'));
    }

    public function test_it_clamps_the_limit_to_the_configured_bounds(): void
    {
        // Arrange

        config(['pulse-mcp.limits.default' => 20, 'pulse-mcp.limits.max' => 50]);

        $pulseRepository = resolve(PulseRepository::class);

        // Act & Assert

        $this->assertSame(20, $pulseRepository->limit(null));

        $this->assertSame(50, $pulseRepository->limit(500));

        $this->assertSame(1, $pulseRepository->limit(0));
    }
}
