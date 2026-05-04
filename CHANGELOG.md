# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-05-XX

### Added
- `HasMcpTools` trait for Eloquent models
- `EloquentMcpServer` abstract base class
- `SchemaGenerator` — auto JSON Schema from `$casts` and column metadata
- Five generic tool classes: `ListModelTool`, `FindModelTool`, `CreateModelTool`, `UpdateModelTool`, `DeleteModelTool`
- Lifecycle hooks: `mcpBeforeCreate`, `mcpCreate`, `mcpUpdate`, `mcpDelete`
- Multi-tenancy support via `mcpScope()`
- UUID primary key auto-detection
- MCP annotations: `ReadOnly`, `Idempotent`, `Destructive`