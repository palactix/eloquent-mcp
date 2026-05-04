<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp\Tools;

use Palactix\EloquentMcp\SchemaGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateModelTool extends Tool
{
    protected string $name;

    protected string $description;

    /**
     * @param  class-string  $modelClass  Model class that uses HasMcpTools
     */
    public function __construct(protected readonly string $modelClass)
    {
        $label = $modelClass::mcpLabel();
        $this->name = 'update_' . $modelClass::mcpSlug();
        $this->description = "Update an existing {$label} by ID. Only provide fields you want to change.";
    }

    public function schema(JsonSchema $schema): array
    {
        $modelClass = $this->modelClass;
        $writable = $modelClass::mcpWritable();
        $properties = SchemaGenerator::forFields($modelClass, $writable, $schema);

        $idType = $modelClass::mcpUsesUuids()
            ? $schema->string()->description('The UUID of the record to update')->required()
            : $schema->integer()->description('The ID of the record to update')->required();

        // id must come first for readability
        return array_merge(['id' => $idType], $properties);
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

        $writable = $modelClass::mcpWritable();
        $data = [];
        foreach ($writable as $field) {
            $value = $request->get($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        if (empty($data)) {
            return Response::error('No fields provided to update.');
        }

        try {
            $modelClass::mcpUpdate($record, $data);
        } catch (\Throwable $e) {
            return Response::error('Failed to update record: ' . $e->getMessage());
        }

        $visible = $modelClass::mcpVisible();
        $fresh = $record->fresh()?->only($visible) ?? $record->only($visible);

        return Response::json([
            'message' => ucfirst($modelClass::mcpLabel()) . ' updated successfully.',
            'data' => $fresh,
        ]);
    }
}
