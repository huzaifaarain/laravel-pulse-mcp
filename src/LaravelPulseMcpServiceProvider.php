<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp;

use HuzaifaArain\LaravelPulseMcp\Http\Middleware\EnsureCanViewPulseMcp;
use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Passport;

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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pulse-mcp');

        $this->defineGate();

        if ($this->app->make(Repository::class)->get('pulse-mcp.enabled')) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/pulse-mcp.php' => config_path('pulse-mcp.php'),
            ], ['pulse-mcp', 'pulse-mcp-config']);

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/pulse-mcp'),
            ], 'pulse-mcp-views');
        }
    }

    /**
     * Default to Pulse's dashboard gate so dashboard viewers are the only MCP users.
     */
    private function defineGate(): void
    {
        $gate = $this->app->make(Gate::class);

        if (! $gate->has('viewPulseMcp')) {
            $gate->define('viewPulseMcp', static fn (mixed $user = null): bool => $gate->forUser($user)->allows('viewPulse'));
        }
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $repository = $this->app->make(Repository::class);
        $usesPassport = $repository->get('pulse-mcp.auth.driver') === 'passport';
        $guard = $repository->get('pulse-mcp.auth.guard') ?? ($usesPassport ? 'api' : 'sanctum');

        if ($usesPassport && $repository->get('pulse-mcp.oauth.routes') && class_exists(Passport::class)) {
            Mcp::oauthRoutes();

            $this->registerAuthorizationView();
        }

        Mcp::web((string) $repository->get('pulse-mcp.path'), PulseServer::class)
            ->middleware([
                ...(array) $repository->get('pulse-mcp.middleware', []),
                'auth:'.$guard,
                EnsureCanViewPulseMcp::class,
            ]);
    }

    /**
     * Provide the package's consent screen unless the application already set a Passport authorization view.
     */
    private function registerAuthorizationView(): void
    {
        $this->app->booted(function (): void {
            $view = $this->app->make(Repository::class)->get('pulse-mcp.oauth.authorization_view');

            if (is_string($view) && $view !== '' && ! $this->app->bound(AuthorizationViewResponse::class)) {
                Passport::authorizationView($view);
            }
        });
    }
}
