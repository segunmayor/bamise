# Module 6 — CRUD Strategies

## Purpose

The Strategy pattern maps each `OperationType` to a discrete, single-responsibility handler that
executes one CRUD operation against the repository layer. `StrategyDispatchHandler` acts as the
context; `OperationStrategyFactory` selects the right strategy at dispatch time.

---

## Strategy Map

| `OperationType` | Strategy class | Repository method(s) used |
|---|---|---|
| `Create` | `CreateStrategy` | `insert()` |
| `Read` (with ID) | `ReadStrategy` | `find()` |
| `Read` (no ID) | `ReadStrategy` | `findAll()` |
| `Update` | `UpdateStrategy` | `update()` |
| `Delete` | `DeleteStrategy` | `delete()` |
| `BulkUpdate` | `BulkUpdateStrategy` | `updateBulk()` |
| `BulkDelete` | `BulkDeleteStrategy` | `deleteBulk()` |

---

## `RepositoryInterface` — Full Contract

```php
interface RepositoryInterface
{
    // Single-record operations
    public function find(ResourceId $id): ?array;
    public function insert(array $data): ResourceId;
    public function update(ResourceId $id, array $data): bool;
    public function delete(ResourceId $id): bool;

    // Collection / bulk operations
    public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array;
    public function updateBulk(array $criteria, array $data): int;
    public function deleteBulk(array $criteria): int;
}
```

All implementations (`PdoRepository`, test fixtures) must provide all seven methods.

---

## Strategy Descriptions

### `CreateStrategy`

1. Resolves resource definition and repository.
2. Applies `FillableGuard` to strip guarded fields (e.g. `id`, `created_at`) from input.
3. Calls `insert()` → receives a `ResourceId`.
4. Returns `CrudResult(success: true, data: [primaryKey => id, ...fields])`.

### `ReadStrategy`

Two execution paths depending on whether a primary-key value is present in `inputData`:

**Single-record path** (`inputData[$primaryKey]` or `inputData['id']` is set):
- Calls `find(ResourceId)`.
- Returns the row or a "Resource not found" failure.

**Collection path** (no ID in input):
- Extracts filter criteria from `inputData` (excludes reserved keys: `id`, primary key, `limit`, `offset`).
- Reads `limit` (default 100) and `offset` (default 0) from `inputData`.
- Calls `findAll($criteria, $limit, $offset)`.
- Returns `CrudResult(success: true, data: ['items' => $rows], meta: ['count' => N])`.

### `UpdateStrategy`

1. Extracts primary-key value from `inputData`.
2. Applies `FillableGuard`, then strips the primary key from the SET payload.
3. Calls `update(ResourceId, data)` → `bool`.
4. Returns merged `[primaryKey => id, ...updatedFields]` or failure.

### `DeleteStrategy`

1. Extracts primary-key value; returns failure if absent.
2. Calls `delete(ResourceId)` → `bool`.
3. Returns `data: [primaryKey => deletedId]` or failure.

### `BulkUpdateStrategy`

Convention for `inputData`:
- `_criteria` (optional `array<string, mixed>`) — the WHERE filter; empty → update all rows.
- All other keys → the data to SET (guarded fields are stripped by `FillableGuard`).

Returns `data: ['affected' => N]` or failure if no data fields remain after guard filtering.

```php
// Example inputData
[
    '_criteria' => ['status' => 'pending'],
    'status'    => 'processed',
]
```

### `BulkDeleteStrategy`

Convention for `inputData`:
- `_criteria` (optional `array<string, mixed>`) — the WHERE filter; empty → delete all rows (use with care).

Returns `data: ['affected' => N]`.

```php
// Example inputData
['_criteria' => ['archived' => true, 'deleted_at' => null]]
```

---

## `SqlCompiler` — Bulk Methods

Three new compile methods support the bulk strategies:

| Method | SQL pattern |
|---|---|
| `compileSelectAll($table, $criteria, $limit, $offset)` | `SELECT * FROM t [WHERE k=v] LIMIT n OFFSET m` |
| `compileUpdateWhere($table, $criteria, $data)` | `UPDATE t SET k=v [WHERE k=v]` |
| `compileDeleteWhere($table, $criteria)` | `DELETE FROM t [WHERE k=v]` |

Criteria binding names use the `__w_` prefix (e.g. `:__w_status`) to avoid collisions with SET
column bindings in `compileUpdateWhere`.
`LIMIT` and `OFFSET` are interpolated as integers (not bound parameters) for broad PDO driver compatibility.

---

## Dispatch Flow

```
CrudApplication::handle($request, $resource)
  → OperationResolver → CrudContext
  → MiddlewarePipeline::handle($context)
      → ... middleware chain ...
      → StrategyDispatchHandler::handle($context)
          → OperationStrategyFactory::for($operation)
          → OperationStrategyInterface::execute($context)
          → CrudResult
      ← CrudResult
  ← CrudResult
  → ResponseMapper → ResponseEnvelope
```

---

## Custom Strategies

Override any operation in `OperationStrategyFactory`:

```php
$factory = new OperationStrategyFactory(
    $repositories,
    $resources,
    $fillableGuard,
    strategies: [
        OperationType::Create->value => new MyAuditedCreateStrategy(...),
    ],
);
```

Or implement `OperationStrategyInterface` and pass a fully custom
`OperationStrategyFactoryInterface` to `StrategyDispatchHandler`.

---

## Related Modules

- [05-middleware.md](05-middleware.md) — pipeline that precedes strategy dispatch
- [04-infrastructure.md](04-infrastructure.md) — `PdoRepository` implements `RepositoryInterface`
- [09-events.md](09-events.md) — `CrudOrchestrator` wraps strategy dispatch with before/after events
