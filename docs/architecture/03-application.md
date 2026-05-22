# Module 3 — Application

The application module orchestrates CRUD requests: resource resolution, middleware pipeline, strategy dispatch, lifecycle events, and HTTP-agnostic response envelopes. It depends only on `Bamise\Contract\` and `Bamise\Domain\`.

## Layout

```
src/Application/
├── CrudApplication.php       # Public entry point
├── DTO/                    # ResponseEnvelope
├── Registry/               # ResourceRegistry
├── Context/                # CrudContextFactory, SubjectFactory, PipelineState
├── Middleware/             # Pipeline + security/validation middleware
├── Handler/                # CrudOrchestrator, StrategyDispatchHandler
├── Strategy/               # OperationStrategyFactory + placeholder strategies
├── Response/               # ResponseMapper, ExceptionMapper
└── Config/                 # ApplicationConfig, MiddlewareConfig
```

## Dependency diagram

```mermaid
flowchart TB
    subgraph contract [Contract]
        Ports[Port interfaces]
        CrudContext
        CrudResult
    end

    subgraph domain [Domain]
        OperationResolver
        PermissionEvaluator
        PolicyEvaluator
        LifecycleEventFactory
    end

    subgraph app [Application]
        CrudApplication
        MiddlewarePipeline
        CrudOrchestrator
        StrategyDispatchHandler
        OperationStrategyFactory
        ResponseMapper
    end

    app --> contract
    app --> domain
```

## PipelineState vs CrudContext

`CrudContext` (Contract) is immutable and is what `MiddlewareInterface::process()` receives.

`PipelineState` (Application) carries:

- Current `CrudContext`
- `ResolvedOperation`
- `ResourceDefinitionInterface`
- Optional domain `Subject`

Middleware **rebuilds** `CrudContext` via `CrudContextFactory` (`withSubject`, `withInputData`) before calling `$next`. `CrudApplication` builds initial `PipelineState`, then passes `fromState()` context into the pipeline.

## Middleware order

Default priorities (lower runs first) from `MiddlewareConfig::defaults()`:

| Priority | Middleware | Port / service |
|----------|------------|----------------|
| 100 | RateLimitMiddleware | RateLimiterPortInterface |
| 200 | AuthenticationMiddleware | AuthPortInterface → SubjectFactory |
| 300 | CsrfMiddleware | CsrfPortInterface (mutations only) |
| 400 | SanitizeMiddleware | SanitizerPortInterface |
| 500 | ValidateMiddleware | ValidatorPortInterface, FillableGuard |
| 600 | AuthorizeMiddleware | PermissionEvaluator, PolicyEvaluator |
| 900 | AuditMiddleware | AuditLoggerPortInterface (after success) |

Terminal handler chain:

`MiddlewarePipeline` → `CrudOrchestrator` (lifecycle events) → `StrategyDispatchHandler` → `OperationStrategyFactory` → placeholder strategy.

## handle() sequence

```mermaid
sequenceDiagram
    participant Client
    participant App as CrudApplication
    participant Reg as ResourceRegistry
    participant OR as OperationResolver
    participant Ctx as CrudContextFactory
    participant Pipe as MiddlewarePipeline
    participant Orch as CrudOrchestrator
    participant Strat as StrategyDispatchHandler
    participant Map as ResponseMapper

    Client->>App: handle(request, resourceName, mode)
    App->>Reg: get(resourceName)
    App->>OR: resolve(request, Resource)
    App->>Ctx: create(resolved, request)
    App->>Pipe: handle(context)
    Note over Pipe: Rate → Auth → CSRF → Sanitize → Validate → Authorize → Audit
    Pipe->>Orch: handle(context)
    Orch->>Orch: dispatch before event
    Orch->>Strat: handle(context)
    Strat-->>Orch: CrudResult
    Orch->>Orch: dispatch after event (on success)
    Orch-->>Pipe: CrudResult
    Pipe-->>App: CrudResult
    App->>Map: map(result, mode)
    Map-->>Client: ResponseEnvelope
```

On exception, `ExceptionMapper` returns a failure `ResponseEnvelope` with an HTTP status hint.

## Placeholder strategies

`CreateStrategy`, `ReadStrategy`, `UpdateStrategy`, and `DeleteStrategy` accept `RepositoryInterface` for future wiring but return `CrudResult` failure (`Infrastructure not wired`). Module 4 will provide PDO repositories and real persistence.

## Ports

Application code imports `Bamise\Contract\*` only. See [`src/Port/README.md`](../../src/Port/README.md).

## Tests

`tests/Unit/Application/` uses fakes in `tests/Fixtures/` for port implementations.

## Next module

**Module 4 — Infrastructure**: PDO connection, repository implementations, CSRF/sanitizer/rate limiter concrete classes, policy class wiring, and strategy persistence.
