# Quick Start

Get a fully working CRUD API running in under 5 minutes. This example uses SQLite in memory —
no database server required.

---

## 1. Create a project

```bash
mkdir my-bamise-app && cd my-bamise-app
composer require bamise/framework
```

---

## 2. Create `app.php`

This single file contains everything: resource definition, request adapter, wiring, and dispatch.
Copy it verbatim.

```php
<?php
// app.php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Bamise\Application\Config\ApplicationConfig;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\CrudApplication;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Middleware\PipelineBuilder;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Response\ExceptionMapper;
use Bamise\Application\Response\ResponseMapper;
use Bamise\Application\Strategy\OperationStrategyFactory;
use Bamise\Contract\Crud\ResourceDefinitionInterface;
use Bamise\Contract\Enum\DatabaseDriver;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\Http\CrudRequestInterface;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Domain\Service\FillableGuard;
use Bamise\Domain\Service\OperationResolver;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Infrastructure\Persistence\PDO\ConnectionConfig;
use Bamise\Infrastructure\Persistence\PDO\PdoConnection;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;

// ── Resource definition ───────────────────────────────────────────────────────
// Describes the "tasks" table: columns, allowed writes, and validation rules.

$taskDefinition = new class implements ResourceDefinitionInterface {
    public function table(): string      { return 'tasks'; }
    public function primaryKey(): string { return 'id'; }
    public function fillable(): array    { return ['title', 'done']; }
    public function guarded(): array     { return ['id']; }
    public function rules(OperationType $op): array { return []; }
    public function policyClasses(): array { return []; }
};

// ── Request adapter ───────────────────────────────────────────────────────────
// Bridges PHP superglobals to Bamise's CrudRequestInterface.

$request = new class implements CrudRequestInterface {
    public function method(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
    public function path(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $p = parse_url($uri, PHP_URL_PATH);
        return is_string($p) ? $p : '/';
    }
    public function input(): array {
        $m = $this->method();
        if ($m === 'POST') return $_POST;
        if (in_array($m, ['PUT', 'PATCH'], true)) {
            parse_str(file_get_contents('php://input') ?: '', $d);
            return $d;
        }
        return $_GET;
    }
    public function all(): array { return $this->input(); }
    public function headers(): array {
        $h = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $h[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }
        return $h;
    }
    public function clientIp(): ?string { return $_SERVER['REMOTE_ADDR'] ?? null; }
};

// ── Database (SQLite in-memory, zero setup) ───────────────────────────────────

$conn = PdoConnection::fromConfig(new ConnectionConfig(
    dsn:      'sqlite::memory:',
    user:     '',
    password: '',
    driver:   DatabaseDriver::Sqlite,
));
$conn->pdo()->exec('
    CREATE TABLE tasks (
        id    INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT    NOT NULL,
        done  INTEGER NOT NULL DEFAULT 0
    )
');

// ── Wiring ────────────────────────────────────────────────────────────────────

$resources  = new ResourceRegistry(['tasks' => $taskDefinition]);
$repos      = new RepositoryResolver([
    'tasks' => (new PdoRepositoryFactory($conn))->for($taskDefinition),
]);
$guard      = new FillableGuard();
$ctxFactory = new CrudContextFactory();
$mapper     = new OperationTypeMapper();

$terminal = new CrudOrchestrator(
    new SyncEventDispatcher(new ListenerRegistry()),
    new LifecycleEventFactory(),
    new StrategyDispatchHandler(
        new OperationStrategyFactory($repos, $resources, $guard)
    ),
);

$pipeline = (new PipelineBuilder())->build($terminal);

$app = new CrudApplication(
    $resources,
    $ctxFactory,
    new OperationResolver($mapper),
    $pipeline,
    new ResponseMapper(),
    new ExceptionMapper(),
    new ApplicationConfig(),
);

// ── Dispatch ──────────────────────────────────────────────────────────────────

$path    = $request->path();
$segment = trim(explode('/', ltrim($path, '/'))[0]);

if ($segment !== 'tasks') {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['message' => 'Not found']]);
    exit;
}

$envelope = $app->handle($request, 'tasks');

http_response_code($envelope->httpStatus);
header('Content-Type: application/json');
echo json_encode([
    'success' => $envelope->success,
    'data'    => $envelope->data,
    'errors'  => $envelope->errors,
    'meta'    => $envelope->meta,
]);
```

---

## 3. Run the server

```bash
php -S localhost:8080 app.php
```

---

## 4. Try it

```bash
# Create a task
curl -s -X POST http://localhost:8080/tasks \
  -d "title=Write+docs&done=0"

# Expected:
# {"success":true,"data":{"title":"Write docs","done":"0","id":1},"errors":[],"meta":{"operation":"create"}}

# List all tasks
curl -s http://localhost:8080/tasks

# Find by ID
curl -s "http://localhost:8080/tasks?id=1"

# Update
curl -s -X PUT http://localhost:8080/tasks \
  -d "id=1&title=Write+docs&done=1"

# Delete
curl -s -X DELETE http://localhost:8080/tasks \
  -d "id=1"
```

---

## What just happened?

- **`ResourceDefinitionInterface`** — declared the `tasks` table shape (fillable columns, primary key).
- **`CrudRequestInterface`** — adapted PHP superglobals to Bamise's request contract.
- **`PdoConnection::fromConfig()`** — opened an SQLite connection and applied the schema.
- **`OperationStrategyFactory`** — registered the six built-in CRUD strategies.
- **`PipelineBuilder::build()`** — created a pass-through pipeline (no middleware — safe only for local testing).
- **`CrudApplication::handle($request, 'tasks')`** — mapped the HTTP verb to an operation, ran the pipeline, and returned a `ResponseEnvelope`.

---

## Next steps

- Add middleware (auth, CSRF, rate limiting) → [Middleware](middleware.md)
- Use a real database → [First Project](first-project.md)
- Add authorization → [Security](security.md)
- Add event listeners → [Events](events.md)
