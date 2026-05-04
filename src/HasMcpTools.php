<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Mcp\Request;

trait HasMcpTools
{
    /**
     * Which CRUD operations to expose via MCP.
     * Override in model: protected static array $mcpTools = ['list', 'find'];
     */
    public static function mcpTools(): array
    {
        return property_exists(static::class, 'mcpTools')
            ? static::$mcpTools
            : ['list', 'find', 'create', 'update', 'delete'];
    }

    /**
     * Fields the AI can use to filter/search in list operations.
     * Override in model: protected static array $mcpSearchable = ['status', 'client_id'];
     */
    public static function mcpSearchable(): array
    {
        if (property_exists(static::class, 'mcpSearchable')) {
            return static::$mcpSearchable;
        }

        return (new static)->getFillable();
    }

    /**
     * Fields visible in MCP responses.
     * Override in model: protected static array $mcpVisible = ['id', 'title', 'status'];
     */
    public static function mcpVisible(): array
    {
        if (property_exists(static::class, 'mcpVisible')) {
            return static::$mcpVisible;
        }

        $model = new static;
        $hidden = $model->getHidden();
        $fields = array_merge(['id'], $model->getFillable(), ['created_at', 'updated_at']);

        return array_values(array_diff(array_unique($fields), $hidden));
    }

    /**
     * Fields the AI can write (create/update).
     * Override in model: protected static array $mcpWritable = ['title', 'status'];
     */
    public static function mcpWritable(): array
    {
        if (property_exists(static::class, 'mcpWritable')) {
            return static::$mcpWritable;
        }

        return (new static)->getFillable();
    }

    /**
     * Relationships to eager-load in MCP responses.
     * Override in model: protected static array $mcpWith = ['client'];
     */
    public static function mcpWith(): array
    {
        return property_exists(static::class, 'mcpWith')
            ? static::$mcpWith
            : [];
    }

    /**
     * Scope MCP queries for multi-tenancy. Override in each model.
     * Default is no-op — models must override for workspace isolation.
     */
    public static function mcpScope(Builder $query, ?Request $_request = null): Builder
    {
        return $query;
    }

    /**
     * Mutate the data array before a create operation.
     * Override in each model to inject server-side fields (e.g. workspace_id, created_by_id).
     */
    public static function mcpBeforeCreate(array $data, Request $request): array
    {
        return $data;
    }

    /**
     * Perform the create operation. Override to add business logic (dispatch a job, send a notification, etc.).
     * Default: simple Eloquent create.
     *
     * @return static
     */
    public static function mcpCreate(array $data): static
    {
        return static::create($data);
    }

    /**
     * Perform the update operation. Override to add business logic.
     * Default: simple Eloquent update.
     */
    public static function mcpUpdate(self $record, array $data): void
    {
        $record->update($data);
    }

    /**
     * Perform the delete operation. Override to add business logic (e.g. cancel jobs, archive instead of delete).
     * Default: Eloquent delete (soft-deletes if SoftDeletes trait is present).
     */
    public static function mcpDelete(self $record): void
    {
        $record->delete();
    }

    /**
     * Human-readable label for the model (used in tool descriptions).
     */
    public static function mcpLabel(): string
    {
        if (property_exists(static::class, 'mcpLabel')) {
            return static::$mcpLabel;
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename(static::class)));
    }

    /**
     * Plural label for list operations.
     */
    public static function mcpPluralLabel(): string
    {
        if (property_exists(static::class, 'mcpPluralLabel')) {
            return static::$mcpPluralLabel;
        }

        return static::mcpLabel() . 's';
    }

    /**
     * Snake_case name for tool naming: "WorkspacePost" → "workspace_post"
     */
    public static function mcpSlug(): string
    {
        if (property_exists(static::class, 'mcpSlug')) {
            return static::$mcpSlug;
        }

        return str(class_basename(static::class))->snake()->toString();
    }

    /**
     * Plural slug for list tool: "workspace_post" → "workspace_posts"
     */
    public static function mcpPluralSlug(): string
    {
        if (property_exists(static::class, 'mcpPluralSlug')) {
            return static::$mcpPluralSlug;
        }

        return str(static::mcpSlug())->plural()->toString();
    }

    /**
     * Whether the model uses UUID primary keys (affects ID schema type).
     */
    public static function mcpUsesUuids(): bool
    {
        return in_array(HasUuids::class, class_uses_recursive(static::class), true);
    }
}
