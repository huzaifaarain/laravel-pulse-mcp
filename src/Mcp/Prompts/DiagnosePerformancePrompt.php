<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Prompts;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('diagnose_performance')]
#[Description('Investigate production performance from Pulse data and propose fixes in the local codebase.')]
class DiagnosePerformancePrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'period' => ['nullable', Rule::in(Period::values())],
            'focus' => ['nullable', 'string', 'max:255'],
        ]);

        $period = (string) ($request->get('period') ?? '24h');
        $focus = $request->string('focus')->toString();

        return Response::text(implode("\n", array_filter([
            "Diagnose this application's production performance over the last {$period} using the Laravel Pulse MCP tools.",
            $focus !== '' ? "Focus on: {$focus}." : null,
            '',
            '1. Call `pulse_health`. If it reports warnings, tell me first, because stale or missing data changes the conclusions.',
            "2. Call `pulse_overview` with period `{$period}` to see which areas are worst.",
            '3. Drill into the worst areas with `pulse_slow_requests`, `pulse_slow_queries`, `pulse_slow_jobs`, `pulse_slow_outgoing_requests`, `pulse_queues` and `pulse_servers`. Use `search` to connect a slow route to the queries it runs.',
            '4. For every finding, open the reported `location` or controller action in the local codebase and read the code before judging it.',
            '5. Report the top issues ranked by impact (slowest_ms x count), each with the evidence from Pulse, the root cause in code, and a concrete fix such as an index, eager loading, caching, chunking, or moving work to a queue.',
            '',
            'Pulse only records entries above each threshold_ms and may sample, so treat counts as indicative. Do not guess values that were redacted.',
        ], static fn (?string $line): bool => $line !== null)));
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('period', 'Time window: 1h, 6h, 24h or 7d. Defaults to 24h.', false),
            new Argument('focus', 'Optional area to focus on, such as a route, job, or feature.', false),
        ];
    }
}
