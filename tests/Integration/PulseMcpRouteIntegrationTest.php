<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\Integration;

use HuzaifaArain\LaravelPulseMcp\Tests\Concerns\UsesAuthDrivers;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Workbench\App\Models\PassportUser;
use Workbench\App\Models\SanctumUser;

final class PulseMcpRouteIntegrationTest extends TestCase
{
    use UsesAuthDrivers;

    public function test_it_does_not_register_the_endpoint_until_enabled(): void
    {
        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->listTools());

        // Assert

        $testResponse->assertNotFound();
    }

    #[DefineEnvironment('usesPassport')]
    public function test_it_challenges_unauthenticated_clients_with_oauth_discovery(): void
    {
        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->listTools());

        // Assert

        $testResponse->assertUnauthorized();

        $testResponse->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.url('.well-known/oauth-protected-resource/mcp/pulse').'"');
    }

    #[DefineEnvironment('usesPassport')]
    public function test_it_publishes_oauth_metadata_with_dynamic_client_registration(): void
    {
        // Act

        $testResponse = $this->getJson('.well-known/oauth-authorization-server');

        // Assert

        $testResponse->assertOk()->assertJson([
            'registration_endpoint' => url('oauth/register'),
            'scopes_supported' => ['mcp:use'],
            'code_challenge_methods_supported' => ['S256'],
        ]);
    }

    #[DefineEnvironment('usesPassport')]
    public function test_it_serves_tools_to_passport_tokens_with_the_mcp_scope(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        Passport::actingAs(PassportUser::factory()->create(), ['mcp:use']);

        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->listTools());

        // Assert

        $testResponse->assertOk();

        $this->assertContains('pulse_overview', array_column($testResponse->json('result.tools'), 'name'));
    }

    #[DefineEnvironment('usesPassport')]
    public function test_it_rejects_passport_tokens_without_the_mcp_scope(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        Passport::actingAs(PassportUser::factory()->create(), []);

        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->listTools());

        // Assert

        $testResponse->assertForbidden()->assertJson(['message' => 'The access token is missing the mcp:use scope.']);
    }

    #[DefineEnvironment('usesPassport')]
    public function test_it_rejects_users_who_cannot_view_pulse(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => false);

        Passport::actingAs(PassportUser::factory()->create(), ['mcp:use']);

        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->listTools());

        // Assert

        $testResponse->assertForbidden()->assertJson(['message' => 'This user may not access Pulse data.']);
    }

    #[DefineEnvironment('usesPassport')]
    public function test_it_prefers_an_application_defined_mcp_gate(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => false);

        Gate::define('viewPulseMcp', static fn (PassportUser $user): bool => $user->email === 'ops@example.com');

        Passport::actingAs(PassportUser::factory()->create(['email' => 'ops@example.com']), ['mcp:use']);

        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->listTools());

        // Assert

        $testResponse->assertOk();
    }

    #[DefineEnvironment('usesSanctum')]
    public function test_it_serves_tools_to_sanctum_tokens(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        Sanctum::actingAs(SanctumUser::factory()->create(), ['*']);

        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->callTool('pulse_health'));

        // Assert

        $testResponse->assertOk()->assertJsonPath('result.isError', false);
    }

    #[DefineEnvironment('usesSanctum')]
    public function test_it_rejects_sanctum_tokens_without_the_mcp_ability(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        Sanctum::actingAs(SanctumUser::factory()->create(), ['reports:read']);

        // Act

        $testResponse = $this->postJson('mcp/pulse', $this->listTools());

        // Assert

        $testResponse->assertForbidden();
    }

    #[DefineEnvironment('usesSanctum')]
    public function test_it_does_not_register_oauth_routes_for_sanctum(): void
    {
        // Act

        $testResponse = $this->getJson('.well-known/oauth-authorization-server');

        // Assert

        $testResponse->assertNotFound();
    }
}
