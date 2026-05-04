<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp\Tools;

use Palactix\EloquentMcp\SchemaGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateModelTool extends Tool
{
    protected string $name;

    protected string $description;

    /**
     * @param  class-string  $modelClass  Model class that uses HasMcpTools
     */
    public function __construct(protected readonly string $modelClass)
    {
        $label = $modelClass::mcpLabel();
        $this->name = 'create_' . $modelClass::mcpSlug();
        $this->description = "Create a new {$label}. Provide the required fields.";
    }

    public function schema(JsonSchema $schema): array
    {
        $modelClass = $this->modelClass;
        $writable = $modelClass::mcpWritable();
        $properties = SchemaGenerator::forFields($modelClass, $writable, $schema);

        // Mark required fields based on DB column constraints
        $required = SchemaGenerator::requiredFieldNames($modelClass, $writable);
        foreach ($required as $field) {
            if (isset($properties[$field])) {
                $properties[$field]->required();
            }
        }

        return $properties;
    }

    public function handle(Request $request): Response
    {
        $modelClass = $this->modelClass;
        $writable = $modelClass::mcpWritable();

        $data = [];
        foreach ($writable as $field) {
            $value = $request->get($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        $data = $modelClass::mcpBeforeCreate($data, $request);

        try {
            $record = $modelClass::mcpCreate($data);
        } catch (\Throwable $e) {
            return Response::error('Failed to create record: ' . $e->getMessage());
        }

        $visible = $modelClass::mcpVisible();
        $fresh = $record->fresh()?->only($visible) ?? $record->only($visible);

        return Response::json([
            'message' => ucfirst($modelClass::mcpLabel()) . ' created successfully.',
            'data' => $fresh,
        ]);
    }
}
