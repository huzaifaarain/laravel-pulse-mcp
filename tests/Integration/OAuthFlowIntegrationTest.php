<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\Integration;

use HuzaifaArain\LaravelPulseMcp\Tests\Concerns\UsesAuthDrivers;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Workbench\App\Models\PassportUser;

final class OAuthFlowIntegrationTest extends TestCase
{
    use UsesAuthDrivers;

    private const string REDIRECT_URI = 'http://127.0.0.1:33418/callback';

    #[DefineEnvironment('usesPassport')]
    public function test_an_mcp_client_can_register_authorize_and_read_pulse_data(): void
    {
        // Arrange

        Gate::define('viewPulse', static fn (): bool => true);

        $user = PassportUser::factory()
            ->create();

        Pulse::record('slow_job', 'App\Jobs\Sync', 2000)->max()->count();

        Pulse::ingest();

        $verifier = Str::random(64);

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        // Act

        $testResponse = $this->postJson('oauth/register', [
            'client_name' => 'Claude Code',
            'redirect_uris' => [self::REDIRECT_URI],
        ]);

        $clientId = (string) $testResponse->json('client_id');

        $consent = $this->actingAs($user, 'web')->get('oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'state-123',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        $approval = $this->actingAs($user, 'web')->post('oauth/authorize', [
            'state' => 'state-123',
            'client_id' => $clientId,
            'auth_token' => session('authToken'),
        ]);

        parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $callback);

        $token = $this->post('oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => $verifier,
            'code' => $callback['code'] ?? '',
        ]);

        $this->app['auth']->forgetGuards();

        $response = $this->withToken((string) $token->json('access_token'))
            ->postJson('mcp/pulse', $this->callTool('pulse_slow_jobs', ['period' => '1h']));

        // Assert

        $testResponse->assertCreated();

        $consent->assertOk()->assertSee('Claude Code');

        $approval->assertRedirect();

        $this->assertSame('state-123', $callback['state'] ?? null);

        $token->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);

        $response->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.rows.0.job', 'App\Jobs\Sync');
    }
}
