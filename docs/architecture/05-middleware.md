# Module 5 — Middleware Pipeline

## Purpose

The middleware pipeline sits between a raw `CrudRequestInterface` and the terminal strategy handler. Each
middleware can inspect, modify, or short-circuit the `CrudContext` before delegating to the next layer, and
can inspect or transform the `CrudResult` on the way back out.

---

## Core Classes

| Class | Role |
|---|---|
| `MiddlewarePipeline` | Chains middleware by ascending priority; `CrudHandlerInterface` itself |
| `DelegateHandler` | Adapter that converts a `MiddlewareInterface + CrudHandlerInterface` pair into a single `CrudHandlerInterface` |
| `PrioritizedMiddleware` | Readonly value-wrapper: `middleware + int priority` |
| `PipelineBuilder` | Fluent builder that accumulates `(middleware, priority)` pairs and produces a `MiddlewarePipeline` |
| `MiddlewareConfig` | Value-object listing class names and priorities — describes the default stack |

---

## Chain of Responsibility Pattern

```
Request
  │
  ▼
MiddlewarePipeline::handle($context)
  │
  ▼  (priority 100)
DelegateHandler → RateLimitMiddleware::process($ctx, $next)
  │
  ▼  (priority 200)
DelegateHandler → AuthenticationMiddleware::process($ctx, $next)
  │
  ▼  (priority 300)
DelegateHandler → CsrfMiddleware::process($ctx, $next)
  │
  ▼  (priority 400)
DelegateHandler → SanitizeMiddleware::process($ctx, $next)
  │
  ▼  (priority 500)
DelegateHandler → ValidateMiddleware::process($ctx, $next)
  │
  ▼  (priority 600)
DelegateHandler → AuthorizeMiddleware::process($ctx, $next)
  │
  ▼  (priority 900)
DelegateHandler → AuditMiddleware::process($ctx, $next)
  │
  ▼  terminal
StrategyDispatchHandler::handle($context)
  │
  ▼
CrudResult (propagates back up)
```

The pipeline is built by collecting `DelegateHandler` wrappers in **reverse priority order** and chaining
them. The outermost handler when `handle()` is called is the lowest-priority middleware.

---

## Priority Ordering

Lower priority number = executes earlier (outer layer). The sort is ascending, then the chain is built
in reverse so that the lowest-priority entry wraps the rest.

| Priority | Middleware | Reason |
|---|---|---|
| 100 | `RateLimitMiddleware` | Reject abusive clients before any DB work |
| 200 | `AuthenticationMiddleware` | Resolve subject identity early |
| 300 | `CsrfMiddleware` | Validate form origin before touching data |
| 400 | `SanitizeMiddleware` | Strip XSS before validation sees the data |
| 500 | `ValidateMiddleware` | Validate and filter fillable fields |
| 600 | `AuthorizeMiddleware` | Gate on permissions after identity is known |
| 900 | `AuditMiddleware` | Log after the operation; highest priority so it wraps all inner results |

`SigningMiddleware` is optional (not in `defaults()`); add it at priority 50–90 for API-key/HMAC flows.

---

## Context Immutability

`CrudContext` is a `readonly` value object. Middleware that needs to mutate state (e.g. populate the
subject after authentication) creates a **new** `CrudContext` instance via `CrudContextFactory` and passes
that to `$next->handle(...)`. The original context is untouched. Changes propagate forward through the
chain; they never propagate backwards.

```php
// AuthenticationMiddleware — context replacement pattern
$subject = $this->subjectFactory->fromAuthSubject($authSubject);
return $next->handle($this->contextFactory->withSubject($context, $subject));
```

---

## Concrete Middleware Descriptions

### `RateLimitMiddleware`
Uses `RateLimiterPortInterface::attempt($key)`. Key is `clientIp()` when present, falling back to
`resource:operation`. Throws `RateLimitException` → HTTP 429.

### `AuthenticationMiddleware`
Calls `AuthPortInterface::authenticate($request)` and `AuthPortInterface::subject()` (session fallback).
Passes result through `SubjectFactory::fromAuthSubject()` to produce a typed `Subject`. Never throws —
an unauthenticated request gets `null` subject, which `AuthorizeMiddleware` will reject.

### `CsrfMiddleware`
Skips non-mutating operations (`Read`). Calls `CsrfPortInterface::validate($request)` on all mutating
operations. Throws `CsrfException` → HTTP 403.

### `SanitizeMiddleware`
Calls `SanitizerPortInterface::sanitize($inputData)` and replaces `inputData` in the context. The
`HtmlSanitizer` strips all HTML tags.

### `ValidateMiddleware`
Applies `FillableGuard` to filter fillable/guarded fields, builds a `FieldBag`, calls
`ValidatorPortInterface::validate()`. Uses sanitized data from the validator result if non-empty.
Throws `ValidationException` → HTTP 422.

### `AuthorizeMiddleware`
Requires a `Subject` (unauthenticated → `AuthorizationException`). Calls `PermissionEvaluator` and
`PolicyEvaluator`. Throws `InsufficientPermissionException` or `AuthorizationException` → HTTP 403.

### `AuditMiddleware`
Runs **after** the inner handler returns. Logs only on mutating, successful operations. Uses
`AuditLoggerPortInterface` with an `AuditRecord` value object.

### `SigningMiddleware` *(optional)*
Validates HMAC signatures via `RequestSignerPortInterface::verify($request)`. Add at priority 50–90 for
API-key authenticated flows where every request must be signed. Throws `AuthorizationException` → HTTP 403.

---

## Building a Pipeline

### With `PipelineBuilder` (recommended)

```php
use Bamise\Application\Middleware\PipelineBuilder;

$pipeline = (new PipelineBuilder())
    ->add(new RateLimitMiddleware($rateLimiter),      100)
    ->add(new AuthenticationMiddleware($auth, ...),   200)
    ->add(new CsrfMiddleware($csrf),                  300)
    ->add(new SanitizeMiddleware($sanitizer, ...),    400)
    ->add(new ValidateMiddleware($validator, ...),    500)
    ->add(new AuthorizeMiddleware($perms, $policy),   600)
    ->add(new AuditMiddleware($auditLogger),          900)
    ->build($strategyHandler);
```

### With `MiddlewarePipeline` directly

```php
use Bamise\Application\Middleware\MiddlewarePipeline;
use Bamise\Application\Middleware\PrioritizedMiddleware;

$pipeline = new MiddlewarePipeline(
    [
        new PrioritizedMiddleware($rateLimitMiddleware, 100),
        new PrioritizedMiddleware($authMiddleware,      200),
        // ...
    ],
    $strategyHandler,
);
```

Plain `MiddlewareInterface` instances (not wrapped in `PrioritizedMiddleware`) are accepted; they receive
auto-priorities of `index × 100` in insertion order.

---

## Short-Circuiting

Any middleware can return a `CrudResult` directly without calling `$next->handle()`:

```php
public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
{
    if ($this->isBlocked($context)) {
        return new CrudResult(success: false, errors: ['message' => 'Blocked']);
    }

    return $next->handle($context);
}
```

This is how `CsrfMiddleware`, `RateLimitMiddleware`, and `AuthorizeMiddleware` short-circuit by
**throwing exceptions** (caught by `CrudApplication` → `ExceptionMapper`).

---

## Adding Custom Middleware

1. Implement `Bamise\Contract\MiddlewareInterface`
2. Constructor-inject any ports or services needed
3. Add via `PipelineBuilder::add()` at an appropriate priority

```php
final class MyCustomMiddleware implements MiddlewareInterface
{
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        // pre-processing ...
        $result = $next->handle($context);
        // post-processing ...
        return $result;
    }
}
```

---

## Related Modules

- [03-application.md](03-application.md) — `CrudApplication` wires the pipeline into the request lifecycle
- [08-security.md](08-security.md) — security ports (`CsrfPortInterface`, `RateLimiterPortInterface`, etc.)
- [06-strategies.md](06-strategies.md) — terminal handler that the pipeline delegates to
