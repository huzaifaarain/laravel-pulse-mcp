<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\Integration;

use HuzaifaArain\LaravelPulseMcp\Tests\Concerns\UsesAuthDrivers;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Recorders\UserRequests;
use Laravel\Sanctum\Sanctum;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Workbench\App\Models\SanctumUser;

final class HardeningIntegrationTest extends TestCase
{
    use UsesAuthDrivers;

    #[DefineEnvironment('usesSanctum')]
    #[DefineEnvironment('throttlesToTwoRequests')]
    public function test_it_rate_limits_the_endpoint(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        Sanctum::actingAs(SanctumUser::factory()->create(), ['*']);

        // Act

        $responses = array_map(fn (): int => $this->postJson('mcp/pulse', $this->listTools())->status(), range(1, 3));

        // Assert

        $this->assertSame([200, 200, 429], $responses);
    }

    #[DefineEnvironment('usesSanctum')]
    #[DefineEnvironment('recordsUserRequests')]
    public function test_it_keeps_its_own_requests_out_of_pulse(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        Route::middleware('auth:sanctum')->get('orders', static fn (): string => 'ok');

        Sanctum::actingAs(SanctumUser::factory()->create(), ['*']);

        // Act

        $this->postJson('mcp/pulse', $this->listTools())->assertOk();

        $this->get('orders')->assertOk();

        // Assert

        $this->assertSame(1, DB::table('pulse_entries')->where('type', 'user_request')->count());
    }

    protected function throttlesToTwoRequests(Application $app): void
    {
        $app['config']->set('pulse-mcp.throttle', '2,1');
    }

    protected function recordsUserRequests(Application $app): void
    {
        $app['config']->set('pulse.recorders', [UserRequests::class => ['enabled' => true, 'sample_rate' => 1, 'ignore' => []]]);
    }
}
