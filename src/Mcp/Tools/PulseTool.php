<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\Period;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * Shared inputs for tools that read Pulse data over a period.
 */
abstract class PulseTool extends Tool
{
    protected const string DEFAULT_PERIOD = '24h';

    protected function periodSchema(JsonSchema $schema): Type
    {
        return $schema->string()
            ->enum(Period::values())
            ->description('Time window to read. Pulse only aggregates these windows. Defaults to 24h.');
    }

    protected function limitSchema(JsonSchema $schema): Type
    {
        return $schema->integer()
            ->min(1)
            ->max((int) config('pulse-mcp.limits.max', 100))
            ->description('Maximum rows to return.');
    }

    protected function searchSchema(JsonSchema $schema, string $fields): Type
    {
        return $schema->string()->description("Case-insensitive text filter over {$fields}.");
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function validate(Request $request, array $rules = []): array
    {
        return $request->validate([
            'period' => ['nullable', Rule::in(Period::values())],
            'limit' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            ...$rules,
        ]);
    }

    protected function period(Request $request): Period
    {
        return Period::from((string) ($request->get('period') ?? static::DEFAULT_PERIOD));
    }

    /**
     * @return array<string, Type>
     */
    protected function rowListSchema(JsonSchema $schema, string $description): array
    {
        return [
            'period' => $schema->string()->required(),
            'rows' => $schema->array()->items($schema->object())->description($description)->required(),
            'truncated' => $schema->boolean()->description('True when more rows exist than were returned.')->required(),
        ];
    }
}
