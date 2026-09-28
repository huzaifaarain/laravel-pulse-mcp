<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests;

use HuzaifaArain\LaravelPulseMcp\LaravelPulseMcpServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Pulse\PulseServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\User;

use function Orchestra\Testbench\default_migration_path;

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
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('pulse.ingest.driver', 'storage');
        $app['config']->set('pulse-mcp.cache_ttl', 0);
        // Auth driver environments pick their own token-aware user model.
        if (! is_subclass_of((string) $app['config']->get('auth.providers.users.model'), User::class)) {
            $app['config']->set('auth.providers.users.model', User::class);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/pulse/database/migrations');
        $this->loadMigrationsFrom(default_migration_path());
    }
}
