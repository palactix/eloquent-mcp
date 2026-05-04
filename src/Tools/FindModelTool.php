<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp\Tools;

use Palactix\EloquentMcp\HasMcpTools;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class FindModelTool extends Tool
{
    protected string $name;

    protected string $description;

    /**
     * @param  class-string  $modelClass  Model class that uses HasMcpTools
     */
    public function __construct(protected readonly string $modelClass)
    {
        /** @var HasMcpTools $modelClass */
        $label = $modelClass::mcpLabel();
        $this->name = 'find_' . $modelClass::mcpSlug();
        $this->description = "Find a single {$label} by its ID. Returns all visible fields and loaded relationships.";
    }

    public function schema(JsonSchema $schema): array
    {
        /** @var HasMcpTools $modelClass */
        $modelClass = $this->modelClass;

        $idType = $modelClass::mcpUsesUuids()
            ? $schema->string()->description('The UUID of the record to find')->required()
            : $schema->integer()->description('The ID of the record to find')->required();

        return ['id' => $idType];
    }

    public function handle(Request $request): Response
    {
        /** @var HasMcpTools $modelClass */
        $modelClass = $this->modelClass;

        $query = $modelClass::query();
        $query = $modelClass::mcpScope($query, $request);

        $with = $modelClass::mcpWith();
        if (! empty($with)) {
            $query->with($with);
        }

        $visible = $modelClass::mcpVisible();
        $record = $query->select($visible)->find($request->get('id'));

        if (! $record) {
            return Response::error("{$modelClass::mcpLabel()} not found.");
        }

        return Response::json($record->toArray());
    }
}
