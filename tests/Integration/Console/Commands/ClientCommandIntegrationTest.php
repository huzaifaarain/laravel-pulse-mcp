<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\Integration\Console\Commands;

use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;

final class ClientCommandIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://app.example.com', 'pulse-mcp.enabled' => true]);
    }

    public function test_it_prints_the_claude_code_oauth_setup(): void
    {
        // Act & Assert

        $this->artisan('pulse-mcp:client')
            ->expectsOutputToContain('claude mcp add --transport http pulse https://app.example.com/mcp/pulse')
            ->expectsOutputToContain('opens your browser to sign in')
            ->assertSuccessful();
    }

    public function test_it_adds_a_bearer_header_for_sanctum(): void
    {
        // Arrange

        config(['pulse-mcp.auth.driver' => 'sanctum']);

        // Act & Assert

        $this->artisan('pulse-mcp:client', ['agent' => 'claude'])
            ->expectsOutputToContain('--header "Authorization: Bearer <token>"')
            ->assertSuccessful();
    }

    public function test_it_prints_the_codex_oauth_setup(): void
    {
        // Act & Assert

        $this->artisan('pulse-mcp:client', ['agent' => 'codex', '--name' => 'prod-pulse'])
            ->expectsOutputToContain('codex mcp add prod-pulse --url https://app.example.com/mcp/pulse')
            ->expectsOutputToContain('codex mcp login prod-pulse')
            ->assertSuccessful();
    }

    public function test_it_prints_the_codex_sanctum_setup(): void
    {
        // Arrange

        config(['pulse-mcp.auth.driver' => 'sanctum']);

        // Act & Assert

        $this->artisan('pulse-mcp:client', ['agent' => 'codex'])
            ->expectsOutputToContain('bearer_token_env_var = "PULSE_MCP_TOKEN"')
            ->assertSuccessful();
    }

    public function test_it_prints_json_config_for_cursor_and_vscode(): void
    {
        // Act & Assert

        $this->artisan('pulse-mcp:client', ['agent' => 'cursor', '--url' => 'https://ops.example.com/mcp/pulse'])
            ->expectsOutputToContain('"url": "https://ops.example.com/mcp/pulse"')
            ->assertSuccessful();

        $this->artisan('pulse-mcp:client', ['agent' => 'vscode'])
            ->expectsOutputToContain('"type": "http"')
            ->assertSuccessful();
    }

    public function test_it_warns_when_the_endpoint_is_disabled(): void
    {
        // Arrange

        config(['pulse-mcp.enabled' => false]);

        // Act & Assert

        $this->artisan('pulse-mcp:client')
            ->expectsOutputToContain('PULSE_MCP_ENABLED=true')
            ->assertSuccessful();
    }

    public function test_it_rejects_unknown_agents(): void
    {
        // Act & Assert

        $this->artisan('pulse-mcp:client', ['agent' => 'notepad'])->assertFailed();
    }
}
