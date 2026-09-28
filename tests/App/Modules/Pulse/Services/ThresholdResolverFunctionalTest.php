<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Modules\Pulse\Services;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\ThresholdResolver;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Laravel\Pulse\Recorders\SlowQueries;

final class ThresholdResolverFunctionalTest extends TestCase
{
    public function test_it_reads_a_scalar_threshold(): void
    {
        // Arrange

        config(['pulse.recorders.'.SlowQueries::class.'.threshold' => 250]);

        // Act & Assert

        $this->assertSame(250, resolve(ThresholdResolver::class)->resolve(SlowQueries::class, 'select 1'));
    }

    public function test_it_matches_a_pattern_threshold_before_the_default(): void
    {
        // Arrange

        config(['pulse.recorders.'.SlowQueries::class.'.threshold' => [
            '/reports/' => 5000,
            'default' => 300,
        ]]);

        $thresholdResolver = resolve(ThresholdResolver::class);

        // Act & Assert

        $this->assertSame(5000, $thresholdResolver->resolve(SlowQueries::class, 'select * from reports'));

        $this->assertSame(300, $thresholdResolver->resolve(SlowQueries::class, 'select * from users'));
    }

    public function test_it_falls_back_to_pulse_default_threshold(): void
    {
        // Arrange

        config(['pulse.recorders' => []]);

        // Act & Assert

        $this->assertSame(1000, resolve(ThresholdResolver::class)->resolve(SlowQueries::class, 'select 1'));
    }
}
