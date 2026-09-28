<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Modules\Pulse\Services;

use Generator;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\KeyDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeyDecoderUnitTest extends TestCase
{
    /**
     * @param  array<string, string|null>  $expected
     */
    #[DataProvider('keys')]
    public function test_it_decodes_pulse_keys_into_named_fields(string $type, ?string $key, array $expected): void
    {
        // Act & Assert

        $this->assertSame($expected, (new KeyDecoder)->decode($type, $key));
    }

    public static function keys(): Generator
    {
        yield 'slow query' => [
            'type' => 'slow_query',
            'key' => '["select * from users","app/Models/User.php:10"]',
            'expected' => ['sql' => 'select * from users', 'location' => 'app/Models/User.php:10'],
        ];

        yield 'slow query without location' => [
            'type' => 'slow_query',
            'key' => '["select 1",null]',
            'expected' => ['sql' => 'select 1', 'location' => null],
        ];

        yield 'exception' => [
            'type' => 'exception',
            'key' => '["RuntimeException","app/Foo.php:3"]',
            'expected' => ['class' => 'RuntimeException', 'location' => 'app/Foo.php:3'],
        ];

        yield 'slow request' => [
            'type' => 'slow_request',
            'key' => '["GET","/users/{user}","UserController@show"]',
            'expected' => ['method' => 'GET', 'route' => '/users/{user}', 'action' => 'UserController@show'],
        ];

        yield 'slow outgoing request' => [
            'type' => 'slow_outgoing_request',
            'key' => '["POST","https://api.example.com/charge"]',
            'expected' => ['method' => 'POST', 'uri' => 'https://api.example.com/charge'],
        ];

        yield 'slow job' => [
            'type' => 'slow_job',
            'key' => 'App\Jobs\SendInvoice',
            'expected' => ['job' => 'App\Jobs\SendInvoice'],
        ];

        yield 'unknown type' => [
            'type' => 'custom_card',
            'key' => 'anything',
            'expected' => ['key' => 'anything'],
        ];

        yield 'malformed tuple' => [
            'type' => 'slow_query',
            'key' => '[not json',
            'expected' => ['sql' => '[not json', 'location' => null],
        ];
    }
}
