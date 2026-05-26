# Routing

Bamise does not include a router. You control routing in your front controller.
Your router extracts a **resource name** from the URL and passes it to
`CrudApplication::handle($request, $resourceName)`.

---

## Basic routing

The simplest approach: map the first URL segment directly to a resource name.

```php
// public/index.php
$path    = $request->path();
$segment = trim(explode('/', ltrim($path, '/'))[0]);

$envelope = $app->handle($request, $segment);
```

`ResourceRegistry` will throw `InvalidArgumentException` if `$segment` is not registered,
which `ExceptionMapper` catches and returns as HTTP 400. Add an explicit check for 404:

```php
$knownResources = ['users', 'posts', 'comments'];

if (!in_array($segment, $knownResources, true)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['message' => 'Not found']]);
    exit;
}

$envelope = $app->handle($request, $segment);
```

---

## Registering multiple resources

Register each resource in both `ResourceRegistry` and `RepositoryResolver`:

```php
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Application\Registry\RepositoryResolver;
use Bamise\Infrastructure\Persistence\Repository\PdoRepositoryFactory;

$factory = new PdoRepositoryFactory($dbConnection);

$userDef    = new UserDefinition();
$postDef    = new PostDefinition();
$commentDef = new CommentDefinition();

$resourceRegistry = new ResourceRegistry([
    'users'    => $userDef,
    'posts'    => $postDef,
    'comments' => $commentDef,
]);

$repositoryResolver = new RepositoryResolver([
    'users'    => $factory->for($userDef),
    'posts'    => $factory->for($postDef),
    'comments' => $factory->for($commentDef),
]);
```

---

## Operation pinning

`RouteOperationConfig` lets you override the operation Bamise derives from the HTTP verb.
This is useful when a route should always perform a specific operation regardless of method.

```php
use Bamise\Contract\ValueObject\RouteOperationConfig;
use Bamise\Contract\Enum\OperationType;

// Always create — even if the HTTP method is GET
$envelope = $app->handle(
    $request,
    'users',
    routeConfig: RouteOperationConfig::pin(OperationType::Create),
);
```

### Allow a restricted set of operations

```php
// Only Read and BulkDelete are permitted on this route.
// Other operations return HTTP 400 (OperationResolutionException).
$envelope = $app->handle(
    $request,
    'users',
    routeConfig: RouteOperationConfig::allow(
        OperationType::Read,
        OperationType::BulkDelete,
    ),
);
```

### Open mode (default)

```php
// No restriction — HTTP verb maps to operation as normal.
$envelope = $app->handle($request, 'users', routeConfig: RouteOperationConfig::open());
```

---

## Client operation hints

Clients can suggest which operation to perform by sending `_crud_operation` in the request
body (or the `data-bamise-crud-op` header). The server validates the hint against:

1. HTTP-method compatibility (a GET cannot hint `create`).
2. The server's `RouteOperationConfig::allow()` set (if set).

```bash
# Bulk delete via DELETE with a hint
curl -X DELETE http://localhost:8080/users \
  -d "_crud_operation=bulk_delete&_criteria[status]=inactive"
```

If the hint is incompatible with the HTTP method, `OperationResolutionException` is thrown
→ HTTP 400. Client hints cannot expand beyond what the server explicitly permits.

**Documented in architecture:** [02-domain.md](architecture/02-domain.md), [06-strategies.md](architecture/06-strategies.md)

---

## Route-level ResponseMode

`ResponseEnvelope` always has a consistent structure. The `ResponseMode` parameter currently
does not affect the output format but is reserved for future Web/API divergence:

```php
use Bamise\Contract\Enum\ResponseMode;

$envelope = $app->handle($request, 'users', mode: ResponseMode::Api);
```

---

## URL patterns and parameter extraction

Bamise does not parse URL path parameters (e.g. `/users/42`). Pass the ID as a
query parameter (`GET /users?id=42`) or in the POST body (`id=42`).

If you want REST-style URL parameters, extract the ID in your router and inject it
into the request input before calling `handle()`. The cleanest way is to implement
`CrudRequestInterface` so that `input()` merges the extracted parameters:

```php
final class RouterRequest implements CrudRequestInterface
{
    /**
     * @param array<string, mixed> $routeParams  extracted by your router (e.g. ['id' => 42])
     */
    public function __construct(
        private readonly CrudRequestInterface $inner,
        private readonly array $routeParams = [],
    ) {}

    public function input(): array
    {
        return array_merge($this->inner->input(), $this->routeParams);
    }

    // delegate everything else to $inner
    public function method(): string  { return $this->inner->method(); }
    public function path(): string    { return $this->inner->path(); }
    public function all(): array      { return $this->inner->all(); }
    public function headers(): array  { return $this->inner->headers(); }
    public function clientIp(): ?string { return $this->inner->clientIp(); }
}

// Usage (after your router extracts the ID from /users/42):
$bamiseRequest = new RouterRequest($phpRequest, ['id' => $routeParams['id']]);
$envelope      = $app->handle($bamiseRequest, 'users');
```

---

## Integrating a third-party router

### Slim 4

```php
use Slim\Factory\AppFactory;

$slim = AppFactory::create();

$slim->get('/users[/{id:[0-9]+}]', function ($req, $res, $args) use ($app) {
    $bamiseRequest = new SlimRequest($req, $args);  // your CrudRequestInterface adapter
    $envelope      = $app->handle($bamiseRequest, 'users');
    $res->getBody()->write(json_encode([
        'success' => $envelope->success,
        'data'    => $envelope->data,
    ]));
    return $res->withHeader('Content-Type', 'application/json')
               ->withStatus($envelope->httpStatus);
});
```

### FastRoute (standalone)

```php
$dispatcher = FastRoute\simpleDispatcher(function ($r) {
    $r->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/users[/{id:\d+}]', 'users');
});

$routeInfo = $dispatcher->dispatch($method, $path);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::FOUND:
        $resourceName = $routeInfo[1];
        $params       = $routeInfo[2];              // ['id' => '42'] if present
        $envelope     = $app->handle(
            new RouterRequest($phpRequest, $params),
            $resourceName,
        );
        sendJson($envelope);
        break;
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        break;
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        break;
}
```

---

## Multiple databases per resource

```php
$primaryConn   = PdoConnection::fromConfig($primaryConfig);
$analyticsConn = PdoConnection::fromConfig($analyticsConfig);

$repoResolver = new RepositoryResolver([
    'users'   => (new PdoRepositoryFactory($primaryConn))->for($userDef),
    'reports' => (new PdoRepositoryFactory($analyticsConn))->for($reportDef),
]);
```

---

## Related

- [CRUD Operations](crud.md) — what each route can do
- [Security](security.md) — per-route authorization
- [Middleware](middleware.md) — per-route middleware via pipeline customisation
