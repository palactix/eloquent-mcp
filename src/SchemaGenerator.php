<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Schema;

class SchemaGenerator
{
    /**
     * Generate JsonSchema Type instances for a set of fields on a model.
     * Returns array<string, Type> suitable for returning from Tool::schema().
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string>  $fields
     * @return array<string, Type>
     */
    public static function forFields(string $modelClass, array $fields, JsonSchema $schema): array
    {
        $model = new $modelClass;
        $table = $model->getTable();
        $casts = $model->getCasts();

        $columns = collect(Schema::getColumns($table))->keyBy('name');

        $properties = [];
        foreach ($fields as $field) {
            $column = $columns->get($field);
            $cast = $casts[$field] ?? null;
            $properties[$field] = static::fieldToType($field, $column, $cast, $schema);
        }

        return $properties;
    }

    /**
     * Return the names of fields that should be required for create operations.
     * A field is required when the column is NOT NULL and has no default value.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string>  $fields
     * @return array<string>
     */
    public static function requiredFieldNames(string $modelClass, array $fields): array
    {
        $model = new $modelClass;
        $table = $model->getTable();

        $columns = collect(Schema::getColumns($table))->keyBy('name');

        $required = [];
        foreach ($fields as $field) {
            $column = $columns->get($field);
            if ($column && ! ($column['nullable'] ?? false) && ($column['default'] === null)) {
                $required[] = $field;
            }
        }

        return $required;
    }

    /**
     * Convert a single field to a JsonSchema Type instance.
     */
    protected static function fieldToType(
        string $field,
        ?array $column,
        mixed $cast,
        JsonSchema $schema,
    ): Type {
        $description = static::fieldDescription($field);

        if ($cast !== null) {
            return static::castToType($cast, $schema)->description($description);
        }

        if ($column !== null) {
            return static::columnToType($column, $schema)->description($description);
        }

        return $schema->string()->description($description);
    }

    /**
     * Map an Eloquent cast to a JsonSchema Type.
     */
    protected static function castToType(mixed $cast, JsonSchema $schema): Type
    {
        // PHP 8.1 backed enum cast
        if (is_string($cast) && enum_exists($cast)) {
            $values = array_column($cast::cases(), 'value');

            return $schema->string()->enum($values);
        }

        return match (true) {
            in_array($cast, ['integer', 'int'], true) => $schema->integer(),
            in_array($cast, ['float', 'double'], true) => $schema->number(),
            str_starts_with((string) $cast, 'decimal') => $schema->number(),
            in_array($cast, ['boolean', 'bool'], true) => $schema->boolean(),
            in_array($cast, ['array', 'json', 'collection'], true) => $schema->array(),
            $cast === 'date' => $schema->string()->format('date'),
            in_array($cast, ['datetime', 'immutable_datetime'], true) => $schema->string()->format('date-time'),
            $cast === 'timestamp' => $schema->integer(),
            default => $schema->string(),
        };
    }

    /**
     * Map a database column type to a JsonSchema Type.
     */
    protected static function columnToType(array $column, JsonSchema $schema): Type
    {
        $typeName = strtolower($column['type_name'] ?? $column['type'] ?? 'varchar');

        $type = match (true) {
            in_array($typeName, ['integer', 'int', 'bigint', 'smallint', 'tinyint', 'mediumint'], true) => $schema->integer(),
            in_array($typeName, ['float', 'double', 'decimal', 'numeric', 'real'], true) => $schema->number(),
            in_array($typeName, ['boolean', 'bool', 'tinyint(1)'], true) => $schema->boolean(),
            in_array($typeName, ['json', 'jsonb'], true) => $schema->array(),
            $typeName === 'date' => $schema->string()->format('date'),
            in_array($typeName, ['datetime', 'timestamp'], true) => $schema->string()->format('date-time'),
            default => $schema->string(),
        };

        if ($column['nullable'] ?? false) {
            $type->nullable();
        }

        return $type;
    }

    /**
     * Generate a human-readable description from a field name.
     * "scheduled_at" → "Scheduled at", "client_member_id" → "Client Member ID"
     */
    protected static function fieldDescription(string $field): string
    {
        return str($field)
            ->replace('_', ' ')
            ->title()
            ->replace(' Id', ' ID')
            ->replace(' At', ' at')
            ->prepend('The ')
            ->toString();
    }
}
