<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp;

use HuzaifaArain\LaravelPulseMcp\Console\Commands\ClientCommand;
use HuzaifaArain\LaravelPulseMcp\Console\Commands\TokenCommand;
use HuzaifaArain\LaravelPulseMcp\Http\Middleware\EnsureCanViewPulseMcp;
use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Passport;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserRequests;

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

            $this->commands([
                ClientCommand::class,
                TokenCommand::class,
            ]);
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

        $path = trim((string) $repository->get('pulse-mcp.path'), '/');
        $throttle = (string) $repository->get('pulse-mcp.throttle');

        Mcp::web($path, PulseServer::class)
            ->middleware(array_values(array_filter([
                ...(array) $repository->get('pulse-mcp.middleware', []),
                $throttle !== '' ? 'throttle:'.$throttle : null,
                'auth:'.$guard,
                EnsureCanViewPulseMcp::class,
            ])));

        if ($repository->get('pulse-mcp.ignore_own_requests', true)) {
            $this->ignoreOwnRequests($repository, $path);
        }
    }

    /**
     * Pulse matches recorder `ignore` patterns against the route path, so agent calls never show up as app traffic.
     */
    private function ignoreOwnRequests(Repository $repository, string $path): void
    {
        $pattern = '#/'.preg_quote($path, '#').'$#';

        foreach ([SlowRequests::class, UserRequests::class] as $recorder) {
            $key = "pulse.recorders.{$recorder}.ignore";

            $repository->set($key, [...(array) $repository->get($key, []), $pattern]);
        }
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
