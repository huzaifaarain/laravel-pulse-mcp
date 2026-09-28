<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\ListUsage;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_usage')]
#[Description('List the users generating the most requests, slow requests, or dispatched jobs in a period. Emails are hidden unless the application opts in.')]
#[IsReadOnly]
#[IsIdempotent]
class UsageTool extends PulseTool
{
    public function handle(Request $request, ListUsage $usage, PulseRepository $repository): ResponseFactory
    {
        $this->validate($request, [
            'type' => ['nullable', Rule::in(array_keys(ListUsage::TYPES))],
        ]);

        $period = $this->period($request);
        $type = (string) ($request->get('type') ?? 'requests');

        return Response::structured([
            'period' => $period->value,
            'type' => $type,
            ...$usage->execute($type, $period, $repository->limit($request->integer('limit') ?: null)),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
            'type' => $schema->string()->enum(array_keys(ListUsage::TYPES))->description('What to rank users by. Defaults to requests.'),
            'limit' => $this->limitSchema($schema),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->required(),
            'type' => $schema->string()->required(),
            'rows' => $schema->array()->items($schema->object())->description('Rows with user_id, name, optional email, and count.')->required(),
            'truncated' => $schema->boolean()->required(),
        ];
    }
}
