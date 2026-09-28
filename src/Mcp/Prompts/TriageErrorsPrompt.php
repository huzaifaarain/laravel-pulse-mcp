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

#[Name('triage_errors')]
#[Description('Triage production exceptions recorded by Pulse and trace each one to its source in the local codebase.')]
class TriageErrorsPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'period' => ['nullable', Rule::in(Period::values())],
        ]);

        $period = (string) ($request->get('period') ?? '24h');

        return Response::text(implode("\n", [
            "Triage the exceptions this application raised in production over the last {$period} using the Laravel Pulse MCP tools.",
            '',
            '1. Call `pulse_health` and mention any warnings about stale data.',
            "2. Call `pulse_exceptions` with period `{$period}`, sorted by count, then again sorted by latest to spot new exceptions.",
            '3. For each exception, open its `location` in the local codebase and read the surrounding code to find the likely cause.',
            '4. Pulse stores no exception messages or stack traces. When the cause is not clear from the code, say which log entry or reproduction step you need from me.',
            '5. Summarise as a prioritised list: exception class, location, count, latest occurrence, likely cause, and proposed fix.',
        ]));
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('period', 'Time window: 1h, 6h, 24h or 7d. Defaults to 24h.', false),
        ];
    }
}
