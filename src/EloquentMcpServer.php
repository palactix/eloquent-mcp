<?php

declare(strict_types=1);

namespace Palactix\EloquentMcp;

use Palactix\EloquentMcp\Tools\CreateModelTool;
use Palactix\EloquentMcp\Tools\DeleteModelTool;
use Palactix\EloquentMcp\Tools\FindModelTool;
use Palactix\EloquentMcp\Tools\ListModelTool;
use Palactix\EloquentMcp\Tools\UpdateModelTool;
use Laravel\Mcp\Server;

/**
 * Abstract MCP server that auto-registers CRUD tools for a set of Eloquent models.
 *
 * Subclass and declare $modelClasses:
 *
 *   protected array $modelClasses = [
 *       WorkspacePost::class,
 *       WorkspaceMember::class,
 *   ];
 *
 * Each model must use the HasMcpTools trait. Tools are built dynamically in boot()
 * based on each model's mcpTools() configuration.
 *
 * Registration in routes/ai.php:
 *   Mcp::web('/mcp/{workspace}', WorkspaceMcpServer::class)->middleware(['auth:sanctum']);
 */
abstract class EloquentMcpServer extends Server
{
    /**
     * Model classes to auto-register tools for.
     * Each class must use the HasMcpTools trait.
     *
     * @var array<int, class-string>
     */
    protected array $modelClasses = [];

    /**
     * Map of tool name → tool class to instantiate.
     *
     * @var array<string, class-string>
     */
    private array $toolMap = [
        'list'   => ListModelTool::class,
        'find'   => FindModelTool::class,
        'create' => CreateModelTool::class,
        'update' => UpdateModelTool::class,
        'delete' => DeleteModelTool::class,
    ];

    /**
     * Called by Server::start() before the transport begins listening.
     * Instantiates tool objects for each model's enabled operations.
     */
    protected function boot(): void
    {
        foreach ($this->modelClasses as $modelClass) {
            if (! in_array(HasMcpTools::class, class_uses_recursive($modelClass), true)) {
                throw new \InvalidArgumentException(
                    "{$modelClass} must use the HasMcpTools trait to be registered with EloquentMcpServer."
                );
            }

            foreach ($modelClass::mcpTools() as $toolName) {
                if (! isset($this->toolMap[$toolName])) {
                    continue;
                }

                $toolClass = $this->toolMap[$toolName];
                $this->tools[] = new $toolClass($modelClass);
            }
        }
    }
}
