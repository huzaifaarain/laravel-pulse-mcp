<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums;

use Laravel\Pulse\Recorders\Exceptions;
use Laravel\Pulse\Recorders\SlowJobs;
use Laravel\Pulse\Recorders\SlowOutgoingRequests;
use Laravel\Pulse\Recorders\SlowQueries;
use Laravel\Pulse\Recorders\SlowRequests;

/**
 * Built-in Pulse entry types recorded with `max` and `count` aggregates.
 */
enum EntryType: string
{
    case SlowQuery = 'slow_query';
    case SlowRequest = 'slow_request';
    case SlowJob = 'slow_job';
    case SlowOutgoingRequest = 'slow_outgoing_request';
    case Exception = 'exception';

    /**
     * @return class-string
     */
    public function recorder(): string
    {
        return match ($this) {
            self::SlowQuery => SlowQueries::class,
            self::SlowRequest => SlowRequests::class,
            self::SlowJob => SlowJobs::class,
            self::SlowOutgoingRequest => SlowOutgoingRequests::class,
            self::Exception => Exceptions::class,
        };
    }

    /**
     * The decoded field Pulse matches recorder thresholds against.
     */
    public function thresholdField(): ?string
    {
        return match ($this) {
            self::SlowQuery => 'sql',
            self::SlowRequest => 'route',
            self::SlowJob => 'job',
            self::SlowOutgoingRequest => 'uri',
            self::Exception => null,
        };
    }

    /**
     * Pulse aggregate to order by for a tool's `sort` input.
     */
    public function orderBy(string $sort): string
    {
        return $sort === 'count' ? 'count' : 'max';
    }

    /**
     * @return list<string>
     */
    public function sorts(): array
    {
        return $this === self::Exception ? ['count', 'latest'] : ['slowest', 'count'];
    }
}
