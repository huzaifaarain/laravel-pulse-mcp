<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_slow_queries')]
#[Description('List database queries slower than the Pulse threshold: SQL (bindings never included, literals may be masked), the code location that ran it, occurrence count and slowest duration in ms.')]
#[IsReadOnly]
#[IsIdempotent]
class SlowQueriesTool extends EntriesTool
{
    protected function type(): EntryType
    {
        return EntryType::SlowQuery;
    }

    protected function searchableFields(): string
    {
        return 'SQL and location';
    }
}
