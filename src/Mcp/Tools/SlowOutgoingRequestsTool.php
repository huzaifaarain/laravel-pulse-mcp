<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_slow_outgoing_requests')]
#[Description('List outgoing HTTP requests (to third-party APIs) that exceeded the Pulse threshold: method, URI (query values redacted), count and slowest duration in ms.')]
#[IsReadOnly]
#[IsIdempotent]
class SlowOutgoingRequestsTool extends EntriesTool
{
    protected function type(): EntryType
    {
        return EntryType::SlowOutgoingRequest;
    }

    protected function searchableFields(): string
    {
        return 'method and URI';
    }
}
