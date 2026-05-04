<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp\Tools;

use Palactix\EloquentMcp\HasMcpTools;
use Palactix\EloquentMcp\SchemaGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListModelTool extends Tool
{
    protected string $name;

    protected string $description;

    /**
     * @param  class-string  $modelClass  Model class that uses HasMcpTools
     */
    public function __construct(protected readonly string $modelClass)
    {
        /** @var HasMcpTools $modelClass */
        $label = $modelClass::mcpPluralLabel();
        $this->name = 'list_' . $modelClass::mcpPluralSlug();
        $this->description = "List and search {$label}. Returns paginated results with optional filters.";
    }

    public function schema(JsonSchema $schema): array
    {
        /** @var HasMcpTools $modelClass */
        $modelClass = $this->modelClass;
        $searchable = $modelClass::mcpSearchable();
        $properties = SchemaGenerator::forFields($modelClass, $searchable, $schema);

        $properties['page'] = $schema->integer()
            ->min(1)
            ->description('Page number (default: 1)');

        $properties['per_page'] = $schema->integer()
            ->min(1)
            ->max(100)
            ->description('Results per page (default: 15, max: 100)');

        return $properties;
    }

    public function handle(Request $request): Response
    {
        /** @var HasMcpTools $modelClass */
        $modelClass = $this->modelClass;

        $query = $modelClass::query();
        $query = $modelClass::mcpScope($query, $request);

        $searchable = $modelClass::mcpSearchable();
        foreach ($searchable as $field) {
            $value = $request->get($field);
            if ($value === null) {
                continue;
            }

            // Date range support: "2024-01-01..2024-12-31"
            if (is_string($value) && str_contains($value, '..')) {
                [$from, $to] = explode('..', $value, 2);
                if ($from !== '') {
                    $query->where($field, '>=', $from);
                }
                if ($to !== '') {
                    $query->where($field, '<=', $to);
                }
            } else {
                $query->where($field, $value);
            }
        }

        $with = $modelClass::mcpWith();
        if (! empty($with)) {
            $query->with($with);
        }

        $visible = $modelClass::mcpVisible();
        $query->select($visible);

        $perPage = min((int) ($request->get('per_page') ?? 15), 100);
        $page = max((int) ($request->get('page') ?? 1), 1);

        $results = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return Response::json([
            'data' => $results->items(),
            'total' => $results->total(),
            'page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'last_page' => $results->lastPage(),
        ]);
    }
}
