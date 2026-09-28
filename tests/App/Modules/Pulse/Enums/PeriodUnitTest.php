<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Modules\Pulse\Enums;

use Generator;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PeriodUnitTest extends TestCase
{
    #[DataProvider('periods')]
    public function test_it_maps_each_period_to_a_pulse_interval(Period $period, int $hours): void
    {
        // Act & Assert

        $this->assertSame($hours, (int) $period->interval()->totalHours);
    }

    public function test_it_lists_the_supported_values(): void
    {
        // Act & Assert

        $this->assertSame(['1h', '6h', '24h', '7d'], Period::values());
    }

    public static function periods(): Generator
    {
        yield 'one hour' => ['period' => Period::OneHour, 'hours' => 1];
        yield 'six hours' => ['period' => Period::SixHours, 'hours' => 6];
        yield 'twenty four hours' => ['period' => Period::TwentyFourHours, 'hours' => 24];
        yield 'seven days' => ['period' => Period::SevenDays, 'hours' => 168];
    }
}
