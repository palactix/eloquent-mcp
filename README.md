# Eloquent MCP

The API Resources layer for MCP. Auto-generate [Model Context Protocol](https://modelcontextprotocol.io) tools from Eloquent models. Add one trait to a model and AI agents can list, find, create, update, and delete records — with full control over what they can see, search, and write.

 
[![Latest Version](https://img.shields.io/packagist/v/palactix/eloquent-mcp.svg?style=flat-square)](https://packagist.org/packages/palactix/eloquent-mcp)
[![PHP Version](https://img.shields.io/packagist/php-v/palactix/eloquent-mcp.svg?style=flat-square)](https://packagist.org/packages/palactix/eloquent-mcp)
[![License](https://img.shields.io/packagist/l/palactix/eloquent-mcp.svg?style=flat-square)](LICENSE)

```php
class Post extends Model
{
    use HasMcpTools; // that's it
}
```

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- [`laravel/mcp`](https://github.com/laravel/mcp)


## The Problem
 
Laravel's official `laravel/mcp` package gives you the primitives:
- Tool
- Resource
- Server
But exposing models to AI requires writing repetitive tool classes. For example:
 
| Models | Required Tool Classes |
|--------|----------------------|
| 1 model | 5 classes |
| 5 models | 25 classes |
| 10 models | **50 classes** |
 
This is the same boilerplate Laravel has eliminated everywhere else:
 
- Controllers → `make:controller --resource`
- Admin panels → Filament / Nova
- APIs → API Resources
But MCP still requires manual wiring.
 
---
 
## The Solution
 
`eloquent-mcp` generates MCP tools directly from your Eloquent models.
 
Add:
```php
use Palactix\EloquentMcp\HasMcpTools;
```
 
Get:
```
list_posts
find_post
create_post
update_post
delete_post
```
 
No manual tool classes.
No manual schemas.
No duplicated logic.
 
---
 
## Why This Matters
 
MCP is quickly becoming the **standard interface for AI agents**.
Without automation, exposing Laravel models to AI requires:
- Writing repetitive tool classes
- Maintaining JSON schemas manually
- Duplicating authorization logic
`eloquent-mcp` applies Laravel's philosophy to MCP:
- Convention over configuration
- Declarative models
- Minimal boilerplate
- Explicit security boundaries
---
 

## Installation

```bash
composer require palactix/eloquent-mcp
```

The service provider is auto-discovered. No other setup needed.

## Basic Usage

### 1. Add the trait to a model

```php
use Palactix\EloquentMcp\HasMcpTools;

class Post extends Model
{
    use HasMcpTools;

    protected $fillable = ['title', 'body', 'status', 'published_at'];
}
```

With no other configuration this exposes all five tools (`list_posts`, `find_post`, `create_post`, `update_post`, `delete_post`) using the fillable fields as the schema.

### 2. Create an MCP server

```php
use Palactix\EloquentMcp\EloquentMcpServer;

class MyMcpServer extends EloquentMcpServer
{
    protected string $name = 'My App MCP Server';
    protected string $version = '1.0.0';

    protected array $modelClasses = [
        Post::class,
    ];
}
```

### 3. Register a route

Create `routes/ai.php` (auto-loaded by `laravel/mcp`):

```php
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', MyMcpServer::class)
    ->middleware(['auth:sanctum']);
```

### 4. Test it

```bash
# List all registered tools
curl -s -X POST https://your-app.com/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | jq .

# List posts
curl -s -X POST https://your-app.com/mcp \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"list_posts","arguments":{"per_page":5}}}' | jq .
```

---

## Advanced Usage

### Restricting which tools are exposed

By default all five operations are exposed. Restrict them with `$mcpTools`:

```php
class Post extends Model
{
    use HasMcpTools;

    // Read-only — AI can browse but not modify
    protected static array $mcpTools = ['list', 'find'];
}
```

Available values: `list`, `find`, `create`, `update`, `delete`.

---

### Controlling field visibility

Use three separate field lists to control what the AI can see, search, and write:

```php
class Post extends Model
{
    use HasMcpTools;

    /** Fields returned in responses */
    protected static array $mcpVisible = [
        'id', 'title', 'status', 'published_at', 'created_at',
    ];

    /** Fields the AI can filter by in list operations */
    protected static array $mcpSearchable = ['status'];

    /** Fields the AI can set on create/update */
    protected static array $mcpWritable = ['title', 'body', 'status', 'published_at'];
}
```

> **Rule of thumb:** keep `$mcpWritable` minimal. Never include fields like `user_id` or `tenant_id` — inject those server-side via `mcpBeforeCreate()`.

---

### Custom labels and tool names

By default the tool names are derived from the class name (`Post` → `list_posts`, `find_post`). Override them when the class name doesn't match what the AI should see:

```php
class WorkspaceMember extends Model
{
    use HasMcpTools;

    // AI-facing name for a single record
    protected static string $mcpLabel = 'client';
    protected static string $mcpPluralLabel = 'clients';

    // Used in tool names: list_clients, find_client
    protected static string $mcpSlug = 'client';
    protected static string $mcpPluralSlug = 'clients';
}
```

This produces tools named `list_clients` and `find_client` instead of `list_workspace_members`.

---

### Multi-tenancy / scoping queries

Override `mcpScope()` to isolate data per tenant, user, or any other boundary. Every list, find, update, and delete query passes through this scope.

```php
class Post extends Model
{
    use HasMcpTools;

    public static function mcpScope(Builder $query, ?Request $request = null): Builder
    {
        // Scope all queries to the authenticated user
        return $query->where('user_id', $request->user()->id);
    }
}
```

#### Multi-tenant route example

With a `{workspace}` route parameter that resolves to a `Workspace` model:

```php
// routes/ai.php
Mcp::web('/mcp/{workspace}', WorkspaceMcpServer::class)
    ->middleware(['auth:sanctum'])
    ->where('workspace', '[a-z0-9-]+');
```

```php
class Post extends Model
{
    use HasMcpTools;

    public static function mcpScope(Builder $query, ?Request $request = null): Builder
    {
        $workspace = request()->route('workspace');

        if ($workspace instanceof Workspace) {
            $query->where('workspace_id', $workspace->id);
        }

        return $query;
    }
}
```

---

### Injecting server-side fields on create

Fields like `user_id`, `workspace_id`, or `created_by_id` should never be settable by AI. Inject them in `mcpBeforeCreate()`, which runs before the record is created and receives the full MCP request:

```php
class Post extends Model
{
    use HasMcpTools;

    protected static array $mcpWritable = ['title', 'body', 'status']; // no user_id here

    public static function mcpBeforeCreate(array $data, Request $request): array
    {
        $data['user_id'] = $request->user()->id;
        return $data;
    }
}
```

For multi-tenant apps, resolve the tenant here too — `mcpScope()` is **not** called during create:

```php
public static function mcpBeforeCreate(array $data, Request $request): array
{
    $workspace = Workspace::where('slug', request()->route('workspace'))->firstOrFail();

    request()->attributes->set('workspace', $workspace); // cache for mcpCreate()

    $data['workspace_id'] = $workspace->id;
    $data['created_by_id'] = $request->user()->id;

    return $data;
}
```

---

### Replacing create/update/delete with business logic

When a simple Eloquent operation isn't enough — dispatching a job, sending a notification, calling a service — override the action hook instead of using the default:

#### `mcpCreate()` — custom creation logic

```php
class Order extends Model
{
    use HasMcpTools;

    public static function mcpCreate(array $data): static
    {
        // Run through the order service instead of direct Eloquent create
        return app(OrderService::class)->createFromMcp($data);
    }
}
```

#### `mcpUpdate()` — custom update logic

```php
class Post extends Model
{
    use HasMcpTools;

    public static function mcpUpdate(self $record, array $data): void
    {
        // Reschedule the publishing job whenever scheduled_at changes
        $record->update($data);

        if (isset($data['scheduled_at'])) {
            PublishPostJob::dispatch($record)->delay($record->scheduled_at);
        }
    }
}
```

#### `mcpDelete()` — custom delete logic

```php
class Post extends Model
{
    use HasMcpTools;

    public static function mcpDelete(self $record): void
    {
        // Cancel any pending jobs before deleting
        CancelPostJobs::dispatch($record);
        $record->delete();
    }
}
```

---

### Eager loading relationships

Use `$mcpWith` to eager-load relationships into every response:

```php
class Post extends Model
{
    use HasMcpTools;

    protected static array $mcpWith = ['author', 'tags'];

    protected static array $mcpVisible = [
        'id', 'title', 'status', 'author', 'tags', 'created_at',
    ];
}
```

---

### Date range filtering

The list tool supports `"from..to"` syntax for any searchable date field automatically:

```bash
# Posts scheduled in May 2026
{
  "name": "list_posts",
  "arguments": {
    "scheduled_at": "2026-05-01..2026-05-31"
  }
}
```

---

### UUID primary keys

Models using Laravel's `HasUuids` trait are detected automatically. The `id` parameter in `find` and `update` tools will use `string` type instead of `integer`:

```php
class Post extends Model
{
    use HasMcpTools;
    use HasUuids; // detected automatically — no extra config needed
}
```

---

### Providing AI behavioral guidelines

Set `$instructions` on your server to guide how the AI agent should use the tools:

```php
class MyMcpServer extends EloquentMcpServer
{
    protected string $name = 'My App MCP Server';
    protected string $version = '1.0.0';

    protected string $instructions = <<<'MD'
        You have access to posts and orders for the authenticated user.

        Guidelines:
        - Never set post status to "published" directly — use "scheduled" with a future date.
        - Always confirm with the user before deleting any record.
        - Order records are read-only — do not attempt to create or modify them.
    MD;

    protected array $modelClasses = [
        Post::class,
        Order::class,
    ];
}
```

---

## Full Model Reference

```php
class Post extends Model
{
    use HasMcpTools;

    // ── Which tools to expose ─────────────────────────────────────────────────

    protected static array $mcpTools = ['list', 'find', 'create', 'update', 'delete'];

    // ── Field control ─────────────────────────────────────────────────────────

    /** Returned in all responses */
    protected static array $mcpVisible = ['id', 'title', 'status', 'published_at', 'created_at'];

    /** Available as filters in the list tool */
    protected static array $mcpSearchable = ['status'];

    /** AI can set these on create/update */
    protected static array $mcpWritable = ['title', 'body', 'status', 'published_at'];

    // ── Labels and tool names ─────────────────────────────────────────────────

    protected static string $mcpLabel = 'post';           // used in descriptions
    protected static string $mcpPluralLabel = 'posts';    // used in list descriptions
    protected static string $mcpSlug = 'post';            // find_post, update_post, delete_post
    protected static string $mcpPluralSlug = 'posts';     // list_posts

    // ── Relationships ─────────────────────────────────────────────────────────

    protected static array $mcpWith = ['author'];

    // ── Hooks ─────────────────────────────────────────────────────────────────

    /** Scope every list/find/update/delete query */
    public static function mcpScope(Builder $query, ?Request $request = null): Builder
    {
        return $query->where('user_id', $request->user()->id);
    }

    /** Mutate data before create (inject server-side fields) */
    public static function mcpBeforeCreate(array $data, Request $request): array
    {
        $data['user_id'] = $request->user()->id;
        return $data;
    }

    /** Replace the create operation */
    public static function mcpCreate(array $data): static
    {
        return static::create($data); // default
    }

    /** Replace the update operation */
    public static function mcpUpdate(self $record, array $data): void
    {
        $record->update($data); // default
    }

    /** Replace the delete operation */
    public static function mcpDelete(self $record): void
    {
        $record->delete(); // default — respects SoftDeletes if present
    }
}
```

---

## Hook Reference

| Hook | Called when | Default behaviour |
|---|---|---|
| `mcpScope($query, $request)` | Every list, find, update, delete | No-op (returns query unchanged) |
| `mcpBeforeCreate($data, $request)` | Before create, after field collection | Returns data unchanged |
| `mcpCreate($data)` | Create operation | `static::create($data)` |
| `mcpUpdate($record, $data)` | Update operation | `$record->update($data)` |
| `mcpDelete($record)` | Delete operation | `$record->delete()` |

---

## Generated Tool Names

Given a model class `WorkspacePost` with default configuration:

| Operation | Tool name |
|---|---|
| List | `list_workspace_posts` |
| Find | `find_workspace_post` |
| Create | `create_workspace_post` |
| Update | `update_workspace_post` |
| Delete | `delete_workspace_post` |

Override `$mcpSlug` / `$mcpPluralSlug` to change these.

---

## Credits

Built by [Palactix](https://palactix.com) — social media infrastructure for agencies.

---

## License

MIT — see [LICENSE](LICENSE).
