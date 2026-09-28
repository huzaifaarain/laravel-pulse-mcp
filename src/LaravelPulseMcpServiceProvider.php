<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp;

use Illuminate\Support\ServiceProvider;

class LaravelPulseMcpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/pulse-mcp.php', 'pulse-mcp');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/pulse-mcp.php' => config_path('pulse-mcp.php'),
        ], ['pulse-mcp', 'pulse-mcp-config']);
    }
}
