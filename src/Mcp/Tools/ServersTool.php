<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\SummarizeServers;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_servers')]
#[Description('Show each server reporting to Pulse: current CPU, memory and disk usage, whether it is still reporting, and average/peak CPU and memory over the period. Requires pulse:check to be running.')]
#[IsReadOnly]
#[IsIdempotent]
class ServersTool extends PulseTool
{
    public function handle(Request $request, SummarizeServers $servers): ResponseFactory
    {
        $this->validate($request, [
            'include_series' => ['nullable', 'boolean'],
        ]);

        $period = $this->period($request);

        return Response::structured([
            'period' => $period->value,
            'servers' => $servers->execute($period, $request->boolean('include_series')),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $this->periodSchema($schema),
            'include_series' => $schema->boolean()->description('Include non-empty per-bucket CPU and memory averages. Defaults to false.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->required(),
            'servers' => $schema->array()->items($schema->object())->required(),
        ];
    }
}
