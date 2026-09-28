<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums;

use Carbon\CarbonInterval;

/**
 * The only windows Pulse pre-aggregates. Any other interval would read raw entries only.
 */
enum Period: string
{
    case OneHour = '1h';
    case SixHours = '6h';
    case TwentyFourHours = '24h';
    case SevenDays = '7d';

    public function interval(): CarbonInterval
    {
        return match ($this) {
            self::OneHour => CarbonInterval::hour(),
            self::SixHours => CarbonInterval::hours(6),
            self::TwentyFourHours => CarbonInterval::hours(24),
            self::SevenDays => CarbonInterval::days(7),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $period): string => $period->value, self::cases());
    }
}
