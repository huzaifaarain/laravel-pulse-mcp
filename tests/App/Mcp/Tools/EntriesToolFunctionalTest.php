<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use Generator;
use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\ExceptionsTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowJobsTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowOutgoingRequestsTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowQueriesTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowRequestsTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Tool;
use Laravel\Pulse\Facades\Pulse;
use PHPUnit\Framework\Attributes\DataProvider;

final class EntriesToolFunctionalTest extends TestCase
{
    /**
     * @param  class-string<Tool>  $tool
     * @param  array<string, mixed>  $expected
     */
    #[DataProvider('tools')]
    public function test_it_lists_decoded_rows_for_each_card(string $tool, string $type, string $key, int $value, array $expected): void
    {
        // Arrange

        Pulse::record($type, $key, $value)->max()->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool($tool, ['period' => '1h']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('period', '1h')
            ->where('truncated', false)
            ->has('rows', 1, fn (AssertableJson $row): AssertableJson => $row->whereAll($expected)->etc()));
    }

    public function test_it_orders_slow_queries_by_count_when_requested(): void
    {
        // Arrange

        Pulse::record('slow_query', json_encode(['select 1', null]), 9000)->max()->count();

        Pulse::record('slow_query', json_encode(['select 2', null]), 1500)->max()->count();

        Pulse::record('slow_query', json_encode(['select 2', null]), 1500)->max()->count();

        Pulse::ingest();

        config(['pulse-mcp.redact.mask_sql_literals' => false]);

        // Act

        $testResponse = PulseServer::tool(SlowQueriesTool::class, ['period' => '1h', 'sort' => 'count']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('rows.0.sql', 'select 2')
            ->where('rows.1.sql', 'select 1')
            ->etc());
    }

    public function test_it_limits_rows_and_flags_truncation(): void
    {
        // Arrange

        Pulse::record('slow_job', 'App\Jobs\A', 3000)->max()->count();

        Pulse::record('slow_job', 'App\Jobs\B', 2000)->max()->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(SlowJobsTool::class, ['period' => '1h', 'limit' => 1]);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('rows', 1)
            ->where('rows.0.job', 'App\Jobs\A')
            ->where('truncated', true)
            ->etc());
    }

    public function test_it_filters_rows_by_search_text(): void
    {
        // Arrange

        Pulse::record('exception', json_encode(['App\Exceptions\PaymentFailed', 'app/Billing.php:9']), now()->getTimestamp())->max()->count();

        Pulse::record('exception', json_encode(['RuntimeException', 'app/Foo.php:3']), now()->getTimestamp())->max()->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(ExceptionsTool::class, ['period' => '1h', 'search' => 'billing']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->has('rows', 1)
            ->where('rows.0.class', 'App\Exceptions\PaymentFailed')
            ->etc());
    }

    public function test_it_rejects_a_sort_the_card_does_not_support(): void
    {
        // Act

        $testResponse = PulseServer::tool(ExceptionsTool::class, ['sort' => 'slowest']);

        // Assert

        $testResponse->assertHasErrors();
    }

    public static function tools(): Generator
    {
        yield 'slow queries' => [
            'tool' => SlowQueriesTool::class,
            'type' => 'slow_query',
            'key' => '["select * from orders","app/Orders.php:4"]',
            'value' => 1200,
            'expected' => ['sql' => 'select * from orders', 'location' => 'app/Orders.php:4', 'count' => 1, 'slowest_ms' => 1200],
        ];

        yield 'exceptions' => [
            'tool' => ExceptionsTool::class,
            'type' => 'exception',
            'key' => '["RuntimeException","app/Foo.php:3"]',
            'value' => 1_790_000_000,
            'expected' => ['class' => 'RuntimeException', 'location' => 'app/Foo.php:3', 'count' => 1],
        ];

        yield 'slow requests' => [
            'tool' => SlowRequestsTool::class,
            'type' => 'slow_request',
            'key' => '["GET","/orders","OrderController@index"]',
            'value' => 2500,
            'expected' => ['method' => 'GET', 'route' => '/orders', 'action' => 'OrderController@index', 'slowest_ms' => 2500],
        ];

        yield 'slow jobs' => [
            'tool' => SlowJobsTool::class,
            'type' => 'slow_job',
            'key' => 'App\Jobs\Sync',
            'value' => 4000,
            'expected' => ['job' => 'App\Jobs\Sync', 'slowest_ms' => 4000],
        ];

        yield 'slow outgoing requests' => [
            'tool' => SlowOutgoingRequestsTool::class,
            'type' => 'slow_outgoing_request',
            'key' => '["POST","https://api.example.com/charge"]',
            'value' => 3000,
            'expected' => ['method' => 'POST', 'uri' => 'https://api.example.com/charge', 'slowest_ms' => 3000],
        ];
    }
}
