<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests;

use HuzaifaArain\LaravelPulseMcp\LaravelPulseMcpServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Pulse\PulseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            PulseServiceProvider::class,
            McpServiceProvider::class,
            LaravelPulseMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('pulse.ingest.driver', 'storage');
        $app['config']->set('pulse-mcp.cache_ttl', 0);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/pulse/database/migrations');
        $this->loadLaravelMigrations();
    }
}
