<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\CheckPulseHealth;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('pulse_health')]
#[Description('Check whether Pulse data is fresh and complete: recording status, ingest driver, latest entry time, reporting servers, and warnings about stopped pulse:work or pulse:check processes. Call this before trusting other Pulse tools.')]
#[IsReadOnly]
#[IsIdempotent]
class HealthTool extends PulseTool
{
    public function handle(Request $request, CheckPulseHealth $health): ResponseFactory
    {
        return Response::structured($health->execute());
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'pulse_enabled' => $schema->boolean()->required(),
            'ingest_driver' => $schema->string()->required(),
            'latest_entry_at' => $schema->string()->nullable()->description('ISO-8601 time of the newest recorded entry.')->required(),
            'servers' => $schema->array()->items($schema->object())->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
