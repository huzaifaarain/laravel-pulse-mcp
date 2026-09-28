<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\Concerns;

use Illuminate\Foundation\Application;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Workbench\App\Models\PassportUser;
use Workbench\App\Models\SanctumUser;

/**
 * Boots Passport and Sanctum next to the package so route tests can enable either auth driver.
 */
trait UsesAuthDrivers
{
    /**
     * @var array{private: string, public: string}|null
     */
    private static ?array $passportKeys = null;

    protected function getPackageProviders($app): array
    {
        return [
            PassportServiceProvider::class,
            SanctumServiceProvider::class,
            ...parent::getPackageProviders($app),
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../vendor/laravel/passport/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/laravel/sanctum/database/migrations');
    }

    protected function usesPassport(Application $app): void
    {
        $app['config']->set('pulse-mcp.enabled', true);
        $app['config']->set('pulse-mcp.auth.driver', 'passport');
        $app['config']->set('auth.guards.api', ['driver' => 'passport', 'provider' => 'users']);
        $app['config']->set('auth.providers.users.model', PassportUser::class);
        $app['config']->set('passport.private_key', self::passportKeys()['private']);
        $app['config']->set('passport.public_key', self::passportKeys()['public']);
    }

    protected function usesSanctum(Application $app): void
    {
        $app['config']->set('pulse-mcp.enabled', true);
        $app['config']->set('pulse-mcp.auth.driver', 'sanctum');
        $app['config']->set('auth.providers.users.model', SanctumUser::class);
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function passportKeys(): array
    {
        if (self::$passportKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

            openssl_pkey_export($key, $private);

            self::$passportKeys = ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
        }

        return self::$passportKeys;
    }

    /**
     * @return array<string, mixed>
     */
    private function listTools(): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function callTool(string $name, array $arguments = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments]];
    }
}
