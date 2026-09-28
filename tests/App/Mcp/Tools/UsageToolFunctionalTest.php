<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\UsageTool;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;
use Laravel\Pulse\Facades\Pulse;
use Workbench\App\Models\User;

final class UsageToolFunctionalTest extends TestCase
{
    public function test_it_ranks_users_by_requests_without_emails(): void
    {
        // Arrange

        $user = User::factory()
            ->create(['name' => 'Ada', 'email' => 'ada@example.com']);

        Pulse::record('user_request', (string) $user->id)->count();

        Pulse::record('user_request', (string) $user->id)->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(UsageTool::class, ['period' => '1h']);

        // Assert

        $testResponse->assertOk()->assertStructuredContent([
            'period' => '1h',
            'type' => 'requests',
            'rows' => [['user_id' => (string) $user->id, 'name' => 'Ada', 'count' => 2]],
            'truncated' => false,
        ]);
    }

    public function test_it_includes_emails_when_the_application_opts_in(): void
    {
        // Arrange

        config(['pulse-mcp.redact.include_user_email' => true]);

        $user = User::factory()
            ->create(['email' => 'ada@example.com']);

        Pulse::record('user_job', (string) $user->id)->count();

        Pulse::ingest();

        // Act

        $testResponse = PulseServer::tool(UsageTool::class, ['period' => '1h', 'type' => 'jobs']);

        // Assert

        $testResponse->assertOk()->assertSee('ada@example.com');
    }

    public function test_it_rejects_unknown_usage_types(): void
    {
        // Act

        $testResponse = PulseServer::tool(UsageTool::class, ['type' => 'logins']);

        // Assert

        $testResponse->assertHasErrors();
    }
}
