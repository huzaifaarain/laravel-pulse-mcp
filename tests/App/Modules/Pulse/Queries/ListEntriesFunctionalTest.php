<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Modules\Pulse\Queries;

use Carbon\CarbonImmutable;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\ListEntries;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Recorders\SlowQueries;

final class ListEntriesFunctionalTest extends TestCase
{
    public function test_it_formats_slow_queries_with_redacted_sql_and_threshold(): void
    {
        // Arrange

        config(['pulse.recorders.'.SlowQueries::class.'.threshold' => 500]);

        Pulse::record('slow_query', json_encode(["select * from users where email = 'a@b.co'", 'app/Models/User.php:12']), 1500)->max()->count();

        Pulse::ingest();

        // Act

        $result = resolve(ListEntries::class)->execute(EntryType::SlowQuery, Period::OneHour, 'slowest', 10);

        // Assert

        $this->assertSame([[
            'sql' => "select * from users where email = '?'",
            'location' => 'app/Models/User.php:12',
            'count' => 1,
            'slowest_ms' => 1500,
            'threshold_ms' => 500,
        ]], $result['rows']);
    }

    public function test_it_reports_the_latest_occurrence_of_exceptions(): void
    {
        // Arrange

        $this->travelTo($now = CarbonImmutable::parse('2026-09-28 10:00:00'));

        Pulse::record('exception', json_encode(['RuntimeException', 'app/Foo.php:3']), $now->getTimestamp())->max()->count();

        Pulse::ingest();

        // Act

        $result = resolve(ListEntries::class)->execute(EntryType::Exception, Period::OneHour, 'count', 10);

        // Assert

        $this->assertSame([[
            'class' => 'RuntimeException',
            'location' => 'app/Foo.php:3',
            'count' => 1,
            'latest_at' => $now->toIso8601String(),
        ]], $result['rows']);
    }

    public function test_it_redacts_outgoing_request_query_values(): void
    {
        // Arrange

        Pulse::record('slow_outgoing_request', json_encode(['GET', 'https://api.example.com/v1?key=secret']), 2000)->max()->count();

        Pulse::ingest();

        // Act

        $result = resolve(ListEntries::class)->execute(EntryType::SlowOutgoingRequest, Period::OneHour, 'slowest', 10);

        // Assert

        $this->assertSame('https://api.example.com/v1?key=[redacted]', $result['rows'][0]['uri']);
    }

    public function test_it_searches_decoded_fields_case_insensitively(): void
    {
        // Arrange

        Pulse::record('slow_request', json_encode(['GET', '/orders', 'OrderController@index']), 2000)->max()->count();

        Pulse::record('slow_request', json_encode(['POST', '/users', 'UserController@store']), 3000)->max()->count();

        Pulse::ingest();

        // Act

        $result = resolve(ListEntries::class)->execute(EntryType::SlowRequest, Period::OneHour, 'slowest', 10, 'ordercontroller');

        // Assert

        $this->assertSame(['/orders'], array_column($result['rows'], 'route'));
    }
}
