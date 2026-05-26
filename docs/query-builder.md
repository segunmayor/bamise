# Query Builder

Bamise's built-in query layer translates HTTP request parameters into parameterised SQL
through `SqlCompiler` and `PdoRepository`. This page explains how filtering, pagination,
and custom queries work.

---

## How the built-in query layer works

`PdoRepository` wraps `SqlCompiler`, which emits safe, dialect-aware SQL. You never write
SQL directly when using the standard CRUD pipeline — queries are built automatically from
request input.

The path from HTTP request to SQL:

```
GET /users?status=active&limit=10&offset=0
        ↓
ReadStrategy extracts criteria = ['status' => 'active'], limit = 10, offset = 0
        ↓
PdoRepository::findAll(['status' => 'active'], 10, 0)
        ↓
SqlCompiler::compileSelectAll → SELECT * FROM "users" WHERE "status" = :__w_status LIMIT 10 OFFSET 0
        ↓
PDO prepared statement + bound parameter
```

---

## Filtering

Any query parameter that is **not** a reserved key becomes a `WHERE` clause with equality (`=`).

**Reserved keys** (never treated as criteria): `id`, the resource's `primaryKey`, `limit`, `offset`.

```bash
# Filter by a single column
curl -s "http://localhost:8080/users?status=active" \
  -H "Authorization: Bearer 1||users.read"

# Filter by multiple columns (AND)
curl -s "http://localhost:8080/users?status=active&role=editor" \
  -H "Authorization: Bearer 1||users.read"
```

Generated SQL:

```sql
-- Single column
SELECT * FROM "users" WHERE "status" = :__w_status LIMIT 100 OFFSET 0

-- Multiple columns
SELECT * FROM "users" WHERE "status" = :__w_status AND "role" = :__w_role LIMIT 100 OFFSET 0
```

**Limitation:** the built-in compiler only supports equality (`=`). For range filters,
`LIKE`, or sub-queries, implement a custom `RepositoryInterface` (see below).

---

## Pagination

Pass `limit` and `offset` as query parameters. Both are applied as `LIMIT n OFFSET m` in SQL.

| Parameter | Default | Description |
|-----------|---------|-------------|
| `limit` | `100` | Maximum rows returned |
| `offset` | `0` | Rows to skip before returning results |

```bash
# First page of 25 rows
curl -s "http://localhost:8080/users?limit=25&offset=0" \
  -H "Authorization: Bearer 1||users.read"

# Second page
curl -s "http://localhost:8080/users?limit=25&offset=25" \
  -H "Authorization: Bearer 1||users.read"
```

Response includes a `count` in `meta` (count of rows returned, not total rows in table):

```json
{
  "success": true,
  "data": {
    "items": [{"id": 1, "name": "Ada Lovelace", "email": "ada@example.com"}]
  },
  "errors": [],
  "meta": {"operation": "read", "count": 1}
}
```

Combine filters with pagination:

```bash
curl -s "http://localhost:8080/users?status=active&limit=10&offset=20" \
  -H "Authorization: Bearer 1||users.read"
```

---

## Find by primary key

Pass the primary key as a query parameter (or in the POST body for non-GET requests).
When `id` (or the resource's `primaryKey`) is present, `ReadStrategy` switches to a
single-row lookup instead of `findAll`.

```bash
# GET with id
curl -s "http://localhost:8080/users?id=42" \
  -H "Authorization: Bearer 1||users.read"
```

Generated SQL:

```sql
SELECT * FROM "users" WHERE "id" = :__pk LIMIT 1
```

---

## Bulk operation criteria

`BulkUpdateStrategy` and `BulkDeleteStrategy` use a `_criteria` key in the request body
to specify the `WHERE` clause. Any additional fillable keys become the `SET` payload.

```bash
# Bulk update: set status=inactive WHERE department=sales
curl -s -X PATCH http://localhost:8080/users \
  -d "_crud_operation=bulk_update&_criteria[department]=sales&status=inactive" \
  -H "Authorization: Bearer 1||users.bulk_update"
```

Generated SQL:

```sql
UPDATE "users" SET "status" = :status WHERE "department" = :__w_department
```

```bash
# Bulk delete: delete WHERE archived=1
curl -s -X DELETE http://localhost:8080/users \
  -d "_crud_operation=bulk_delete&_criteria[archived]=1" \
  -H "Authorization: Bearer 1||users.bulk_delete"
```

**Warning:** sending `_crud_operation=bulk_delete` with an empty `_criteria` deletes all rows.

---

## Using the repository directly

If you need queries that go beyond what the CRUD pipeline offers (joins, aggregates, raw SQL),
inject the `RepositoryResolver` into a service and call the repository directly.

```php
use Bamise\Application\Registry\RepositoryResolver;

// Get the repository for a resource
$repo = $repositoryResolver->for('users');

// List all with criteria and pagination
$rows = $repo->findAll(['status' => 'active'], limit: 20, offset: 0);

// Find by primary key
$user = $repo->find(new \Bamise\Contract\ValueObject\ResourceId(42));

// All rows (caution: no limit by default)
$all = $repo->findAll();
```

---

## Implementing a custom repository

For advanced queries (range filters, joins, full-text search), implement `RepositoryInterface`
and register it in `RepositoryResolver`.

```php
<?php
declare(strict_types=1);

namespace App\Persistence;

use Bamise\Contract\Persistence\RepositoryInterface;
use Bamise\Contract\ValueObject\ResourceId;

final class UserRepository implements RepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findAll(array $criteria = [], int $limit = 100, int $offset = 0): array
    {
        // Build a custom query — e.g. full-text search or range filter
        $sql = 'SELECT * FROM users WHERE 1=1';
        $bindings = [];

        if (isset($criteria['search'])) {
            $sql .= ' AND (name LIKE :search OR email LIKE :search)';
            $bindings[':search'] = '%' . $criteria['search'] . '%';
        }

        if (isset($criteria['created_after'])) {
            $sql .= ' AND created_at > :created_after';
            $bindings[':created_after'] = $criteria['created_after'];
        }

        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function find(ResourceId $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id->value]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function insert(array $data): ResourceId
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
        $stmt->execute($data);
        return new ResourceId((int) $this->pdo->lastInsertId());
    }

    public function update(ResourceId $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
        return $stmt->execute(['name' => $data['name'], 'id' => $id->value]);
    }

    public function delete(ResourceId $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id->value]);
        return $stmt->rowCount() > 0;
    }

    public function updateBulk(array $criteria, array $data): int
    {
        // custom bulk update implementation
        return 0;
    }

    public function deleteBulk(array $criteria): int
    {
        // custom bulk delete implementation
        return 0;
    }
}
```

Register the custom repository instead of the factory-generated one:

```php
$repositoryResolver = new RepositoryResolver([
    'users' => new UserRepository($pdoInstance),
]);
```

---

## QueryBuilderInterface

Bamise ships a `QueryBuilderInterface` contract in the `Contract\Persistence` namespace.
Implement it to create a fluent query builder that can be injected into custom strategies
or services.

```php
use Bamise\Contract\Persistence\QueryBuilderInterface;

interface QueryBuilderInterface
{
    public function table(string $table): self;
    public function select(array $columns = ['*']): self;
    public function where(string $column, mixed $operator, mixed $value = null): self;
    public function orderBy(string $column, string $direction = 'asc'): self;
    public function limit(int $limit): self;
    public function offset(int $offset): self;
    /** @return list<array<string, mixed>> */
    public function get(): array;
    /** @return array<string, mixed>|null */
    public function first(): ?array;
}
```

The built-in `PdoRepository` does **not** expose this interface — it is provided for custom
implementations that want a fluent API.

---

## SqlCompiler reference

`SqlCompiler` is an internal class used by `PdoRepository`. It is not part of the public API,
but understanding its output helps when debugging queries.

| Method | SQL emitted |
|--------|-------------|
| `compileSelectById` | `SELECT * FROM t WHERE pk = :__pk LIMIT 1` |
| `compileSelectAll` | `SELECT * FROM t [WHERE col = :__w_col ...] LIMIT n OFFSET m` |
| `compileInsert` | `INSERT INTO t (col,...) VALUES (:col,...) [RETURNING pk]` |
| `compileUpdate` | `UPDATE t SET col = :col [,...] WHERE pk = :__pk` |
| `compileUpdateWhere` | `UPDATE t SET col = :col WHERE crit = :__w_crit` |
| `compileDelete` | `DELETE FROM t WHERE pk = :__pk` |
| `compileDeleteWhere` | `DELETE FROM t [WHERE crit = :__w_crit]` |

PostgreSQL dialect emits `RETURNING pk` on insert; SQLite and MySQL use `lastInsertId()`.

---

## Related

- [CRUD Operations](crud.md) — per-operation behaviour
- [Routing](routing.md) — how resource names are resolved
- Architecture: [04-infrastructure.md](architecture/04-infrastructure.md)
