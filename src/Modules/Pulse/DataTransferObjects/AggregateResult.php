<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Modules\Pulse\DataTransferObjects;

final readonly class AggregateResult
{
    /**
     * @param  list<\stdClass>  $rows
     */
    public function __construct(
        public array $rows,
        public bool $truncated,
    ) {}
}
