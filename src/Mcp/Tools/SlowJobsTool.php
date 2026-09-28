<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_slow_jobs')]
#[Description('List queued jobs whose execution exceeded the Pulse threshold, with count and slowest duration in ms.')]
#[IsReadOnly]
#[IsIdempotent]
class SlowJobsTool extends EntriesTool
{
    protected function type(): EntryType
    {
        return EntryType::SlowJob;
    }

    protected function searchableFields(): string
    {
        return 'job class';
    }
}
