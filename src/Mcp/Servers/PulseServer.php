<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Servers;

use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\AggregateTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\CacheTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\ExceptionsTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\HealthTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\OverviewTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\QueuesTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\ServersTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowJobsTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowOutgoingRequestsTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowQueriesTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\SlowRequestsTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\TypesTool;
use HuzaifaArain\LaravelPulseMcp\Mcp\Tools\UsageTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Laravel Pulse')]
#[Version('0.1.0')]
#[Instructions(<<<'MARKDOWN'
    Read-only access to this Laravel application's production monitoring data from Laravel Pulse.

    - Start with `pulse_health` to confirm data is fresh, then `pulse_overview`, then the specific tool.
    - Periods are limited to 1h, 6h, 24h and 7d. Pulse keeps at most 7 days.
    - Durations are milliseconds. Slow entries are only recorded above a threshold (`threshold_ms`), and recorders may sample, so counts are indicative rather than exact.
    - Exceptions include class, location and counts only. Pulse does not store messages or stack traces; ask the user for logs.
    - SQL never includes bindings, and literals, URL query values and user emails may be redacted.
    - `location` values are `path:line` relative to the application root; open them in the local codebase to investigate.
    MARKDOWN)]
class PulseServer extends Server
{
    protected array $tools = [
        HealthTool::class,
        OverviewTool::class,
        ExceptionsTool::class,
        SlowQueriesTool::class,
        SlowRequestsTool::class,
        SlowJobsTool::class,
        SlowOutgoingRequestsTool::class,
        QueuesTool::class,
        CacheTool::class,
        ServersTool::class,
        UsageTool::class,
        TypesTool::class,
        AggregateTool::class,
    ];
}
