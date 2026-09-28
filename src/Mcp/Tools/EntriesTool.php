<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Mcp\Tools;

use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Enums\EntryType;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Queries\ListEntries;
use HuzaifaArain\LaravelPulseMcp\Modules\Pulse\Services\PulseRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Lists one slow or exception card's rows with sorting, search and limits.
 */
abstract class EntriesTool extends PulseTool
{
    abstract protected function type(): EntryType;

    abstract protected function searchableFields(): string;

    public function handle(Request $request, ListEntries $entries, PulseRepository $repository): ResponseFactory
    {
        $this->validate($request, [
            'sort' => ['nullable', Rule::in($this->type()->sorts())],
        ]);

        $period = $this->period($request);

        $result = $entries->execute(
            $this->type(),
            $period,
            (string) ($request->get('sort') ?? $this->type()->sorts()[0]),
            $repository->limit($request->integer('limit') ?: null),
            $request->string('search')->toString() ?: null,
        );

        return Response::structured([
            'period' => $period->value,
            ...$result,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        $sorts = $this->type()->sorts();

        return [
            'period' => $this->periodSchema($schema),
            'sort' => $schema->string()->enum($sorts)->description('Row ordering. Defaults to '.$sorts[0].'.'),
            'limit' => $this->limitSchema($schema),
            'search' => $this->searchSchema($schema, $this->searchableFields()),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return $this->rowListSchema($schema, $this->type() === EntryType::Exception
            ? 'Rows with class, location, count and latest_at.'
            : 'Rows with decoded fields, count, slowest_ms and threshold_ms.');
    }
}
