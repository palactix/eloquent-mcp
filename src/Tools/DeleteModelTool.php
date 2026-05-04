<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteModelTool extends Tool
{
    protected string $name;

    protected string $description;

    /**
     * @param  class-string  $modelClass  Model class that uses HasMcpTools
     */
    public function __construct(protected readonly string $modelClass)
    {
        $label = $modelClass::mcpLabel();
        $this->name = 'delete_' . $modelClass::mcpSlug();
        $this->description = "Delete a {$label} by ID. Uses soft-delete if the model supports it. This action cannot be easily undone.";
    }

    public function schema(JsonSchema $schema): array
    {
        $modelClass = $this->modelClass;

        $idType = $modelClass::mcpUsesUuids()
            ? $schema->string()->description('The UUID of the record to delete')->required()
            : $schema->integer()->description('The ID of the record to delete')->required();

        return ['id' => $idType];
    }

    public function handle(Request $request): Response
    {
        $modelClass = $this->modelClass;

        $query = $modelClass::query();
        $query = $modelClass::mcpScope($query, $request);

        $record = $query->find($request->get('id'));

        if (! $record) {
            return Response::error("{$modelClass::mcpLabel()} not found.");
        }

        $identifier = $record->title ?? $record->name ?? "#{$record->getKey()}";

        try {
            $modelClass::mcpDelete($record);
        } catch (\Throwable $e) {
            return Response::error('Failed to delete record: ' . $e->getMessage());
        }

        return Response::text(ucfirst($modelClass::mcpLabel()) . " \"{$identifier}\" deleted successfully.");
    }
}
