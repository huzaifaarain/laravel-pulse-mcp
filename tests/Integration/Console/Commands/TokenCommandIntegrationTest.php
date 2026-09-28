<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\Integration\Console\Commands;

use HuzaifaArain\LaravelPulseMcp\Tests\Concerns\UsesAuthDrivers;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Workbench\App\Models\SanctumUser;
use Workbench\App\Models\User;

final class TokenCommandIntegrationTest extends TestCase
{
    use UsesAuthDrivers;

    #[DefineEnvironment('usesSanctum')]
    public function test_it_issues_a_token_that_can_call_the_mcp_endpoint(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        $user = SanctumUser::factory()
            ->create();

        // Act

        $exitCode = Artisan::call('pulse-mcp:token', ['user' => $user->id]);

        preg_match('/^(\d+\|\S+)$/m', Artisan::output(), $matches);

        $testResponse = $this->withToken($matches[1] ?? '')->postJson('mcp/pulse', $this->listTools());

        // Assert

        $this->assertSame(0, $exitCode);

        $this->assertSame(['mcp:use'], $user->tokens()->sole()->abilities);

        $testResponse->assertOk();
    }

    #[DefineEnvironment('usesSanctum')]
    public function test_it_finds_users_by_email_and_sets_name_and_expiry(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        $user = SanctumUser::factory()
            ->create(['email' => 'ops@example.com']);

        // Act

        $this->artisan('pulse-mcp:token', ['user' => 'ops@example.com', '--name' => 'laptop', '--expires' => 30])
            ->expectsOutputToContain('claude mcp add --transport http pulse')
            ->assertSuccessful();

        // Assert

        $token = $user->tokens()->sole();

        $this->assertSame('laptop', $token->name);

        $this->assertTrue($token->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    #[DefineEnvironment('usesSanctum')]
    public function test_it_warns_when_the_user_fails_the_gate(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => false);

        $user = SanctumUser::factory()
            ->create();

        // Act & Assert

        $this->artisan('pulse-mcp:token', ['user' => $user->id])
            ->expectsOutputToContain('fails the viewPulseMcp gate')
            ->assertSuccessful();
    }

    #[DefineEnvironment('usesSanctum')]
    public function test_it_fails_for_unknown_users(): void
    {
        // Act & Assert

        $this->artisan('pulse-mcp:token', ['user' => 999])->assertFailed();
    }

    #[DefineEnvironment('usesSanctum')]
    public function test_it_rejects_models_without_sanctum_tokens(): void
    {
        // Arrange

        $user = User::factory()
            ->create();

        // Act & Assert

        $this->artisan('pulse-mcp:token', ['user' => $user->id, '--model' => User::class])
            ->expectsOutputToContain('must be an Eloquent model using Laravel\Sanctum\HasApiTokens')
            ->assertFailed();
    }
}
