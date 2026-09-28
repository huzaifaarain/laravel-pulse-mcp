<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App\Mcp\Prompts;

use HuzaifaArain\LaravelPulseMcp\Mcp\Prompts\DiagnosePerformancePrompt;
use HuzaifaArain\LaravelPulseMcp\Mcp\Prompts\TriageErrorsPrompt;
use HuzaifaArain\LaravelPulseMcp\Mcp\Servers\PulseServer;
use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;

final class PromptsFunctionalTest extends TestCase
{
    public function test_it_builds_the_performance_diagnosis_for_the_requested_period_and_focus(): void
    {
        // Act

        $testResponse = PulseServer::prompt(DiagnosePerformancePrompt::class, ['period' => '6h', 'focus' => 'checkout']);

        // Assert

        $testResponse->assertOk()
            ->assertName('diagnose_performance')
            ->assertSee(['over the last 6h', 'Focus on: checkout.', '`pulse_health`', '`pulse_overview`']);
    }

    public function test_it_builds_the_error_triage_for_the_default_period(): void
    {
        // Act

        $testResponse = PulseServer::prompt(TriageErrorsPrompt::class);

        // Assert

        $testResponse->assertOk()
            ->assertName('triage_errors')
            ->assertSee(['over the last 24h', '`pulse_exceptions`', 'no exception messages or stack traces']);
    }

    public function test_it_rejects_unsupported_periods(): void
    {
        // Act

        $testResponse = PulseServer::prompt(TriageErrorsPrompt::class, ['period' => '2d']);

        // Assert

        $testResponse->assertHasErrors();
    }
}
