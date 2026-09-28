<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_slow_requests')]
#[Description('List HTTP routes whose requests exceeded the Pulse threshold: method, route pattern, controller action, count and slowest duration in ms.')]
#[IsReadOnly]
#[IsIdempotent]
class SlowRequestsTool extends EntriesTool
{
    protected function type(): EntryType
    {
        return EntryType::SlowRequest;
    }

    protected function searchableFields(): string
    {
        return 'method, route and action';
    }
}
