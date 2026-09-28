<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_exceptions')]
#[Description('List exceptions reported in production by class and code location, with occurrence count and latest occurrence time. Pulse stores no exception messages or stack traces.')]
#[IsReadOnly]
#[IsIdempotent]
class ExceptionsTool extends EntriesTool
{
    protected function type(): EntryType
    {
        return EntryType::Exception;
    }

    protected function searchableFields(): string
    {
        return 'exception class and location';
    }
}
