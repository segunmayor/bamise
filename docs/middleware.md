# Middleware

Middleware sits between the HTTP layer and the strategy that executes a CRUD operation.
Each middleware implements `MiddlewareInterface`, receives the current `CrudContext`, can
read and pass on that context, and calls `$next->handle($context)` to continue the chain.

---

## How the pipeline works

`PipelineBuilder` collects middleware in priority order. Lower priority numbers run first.
The terminal handler (a `CrudOrchestrator`) runs last and executes the actual CRUD strategy.

```
Request → [priority 100] → [priority 200] → ... → [terminal handler]
```

```php
use Bamise\Application\Middleware\PipelineBuilder;

$pipeline = (new PipelineBuilder())
    ->add($middlewareA, 100)   // runs first
    ->add($middlewareB, 200)   // runs second
    ->add($middlewareC, 300)   // runs third
    ->build($terminal);        // terminal runs last
```

`$pipeline` is a `MiddlewarePipeline` that implements `CrudHandlerInterface`. Pass it to
`CrudApplication` as the fourth constructor argument.

---

## Built-in middleware

| Class | Priority (recommended) | Purpose |
|-------|------------------------|---------|
| `RateLimitMiddleware` | 100 | Enforces per-IP request rate limits |
| `AuthenticationMiddleware` | 200 | Authenticates the request subject |
| `CsrfMiddleware` | 300 | Validates CSRF tokens for state-changing requests |
| `SanitizeMiddleware` | 400 | HTML-encodes string input fields |
| `ValidateMiddleware` | 500 | Runs validation rules from `ResourceDefinitionInterface` |
| `AuthorizeMiddleware` | 600 | Checks permissions and evaluates policies |
| `AuditMiddleware` | 700 | Logs mutating operations to an audit logger |
| `SigningMiddleware` | any | Verifies HMAC request signatures |

Priorities are conventions, not requirements. Assign any integer that gives the correct ordering.

---

## Writing a custom middleware

Implement `Bamise\Contract\MiddlewareInterface`:

```php
<?php
declare(strict_types=1);

namespace App\Middleware;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

final class TimestampMiddleware implements MiddlewareInterface
{
    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        // Inject a server-side timestamp into the input data before the strategy runs.
        $input = array_merge($context->inputData, ['updated_at' => date('Y-m-d H:i:s')]);

        // CrudContext is readonly — create a new instance with the mutated field.
        $modified = new CrudContext(
            request:      $context->request,
            resourceName: $context->resourceName,
            operation:    $context->operation,
            inputData:    $input,
            subject:      $context->subject,
        );

        return $next->handle($modified);
    }
}
```

Register it in the pipeline:

```php
$pipeline = (new PipelineBuilder())
    ->add(new TimestampMiddleware(), 450)
    ->build($terminal);
```

---

## CrudContext

`CrudContext` is the immutable value object passed through the pipeline.

```php
readonly class CrudContext
{
    public function __construct(
        public CrudRequestInterface $request,
        public string               $resourceName,
        public OperationType        $operation,
        public array                $inputData,   // array<string, mixed>
        public ?object              $subject,     // authenticated subject, or null
    ) {}
}
```

Because it is readonly, modify it by constructing a new instance with changed fields
(as shown in `TimestampMiddleware` above).

---

## CrudResult

`CrudResult` is the immutable value object returned from `$next->handle()`.

```php
readonly class CrudResult
{
    public function __construct(
        public bool  $success,
        public array $data   = [],
        public array $errors = [],
        public array $meta   = [],
    ) {}
}
```

---

## Short-circuiting the pipeline

Return a `CrudResult` without calling `$next->handle()` to stop the chain immediately.
This is how `AuthorizeMiddleware` rejects unauthorised requests:

```php
public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
{
    if (! $this->isAllowed($context)) {
        // Return early — do NOT call $next->handle()
        return new CrudResult(
            success: false,
            errors: ['message' => 'Forbidden'],
        );
    }

    return $next->handle($context);
}
```

Throwing a `BamiseException` subclass is the preferred way to abort — `ExceptionMapper`
converts it to the correct HTTP status code automatically:

```php
use Bamise\Contract\Exception\AuthorizationException;

throw new AuthorizationException('Access denied.');
// → HTTP 403
```

---

## RateLimitMiddleware

```php
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Cache\InMemoryCache;

$rateLimiter = new CacheRateLimiter(
    new InMemoryCache(),
    new RateLimitConfig(maxAttempts: 60, windowSeconds: 60),
);

->add(new RateLimitMiddleware($rateLimiter), 100)
```

For production (multiple PHP-FPM workers), use `RedisRateLimiter` instead of
`CacheRateLimiter` so the limit is shared across workers.

`RateLimitException` → HTTP 429.

---

## AuthenticationMiddleware

```php
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;

->add(new AuthenticationMiddleware(
    new BearerTokenAuthAdapter(),   // AuthPortInterface
    new SubjectFactory(),
    new CrudContextFactory(),
), 200)
```

The adapter populates `CrudContext::subject`. If authentication fails, `subject` is `null`
(the request continues — `AuthorizeMiddleware` enforces the deny).

---

## CsrfMiddleware

```php
use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Infrastructure\Cache\InMemoryCache;

$csrfService = new SessionCsrfService(
    new InMemoryCache(),
    new CsrfTokenGenerator(),
    new CsrfConfig(),   // fieldName='_csrf', sessionField='_session_id', ttl=3600
);

->add(new CsrfMiddleware($csrfService), 300)
```

`CsrfMiddleware` only checks POST, PUT, PATCH, and DELETE requests; GET/HEAD pass through.
`CsrfException` → HTTP 403.

---

## SanitizeMiddleware

```php
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Infrastructure\Security\Sanitizer\HtmlSanitizer;
use Bamise\Infrastructure\Security\Sanitizer\SanitizerConfig;

->add(new SanitizeMiddleware(
    new HtmlSanitizer(new SanitizerConfig()),
    new CrudContextFactory(),
), 400)
```

`HtmlSanitizer` applies `htmlspecialchars` to all string values in `inputData`. Configure
which tags are allowed via `SanitizerConfig`.

---

## ValidateMiddleware

Bamise does **not** ship a validator implementation. You must supply one.

```php
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Middleware\ValidateMiddleware;
use Bamise\Application\Registry\ResourceRegistry;
use Bamise\Contract\ValidatorPortInterface;
use Bamise\Contract\ValueObject\ValidationResult;
use Bamise\Domain\Service\FillableGuard;

// Passthrough validator (skips all validation)
$validator = new class implements ValidatorPortInterface {
    public function validate(array $data, array $rules): ValidationResult
    {
        return new ValidationResult(valid: true);
    }
};

->add(new ValidateMiddleware(
    $validator,
    $resourceRegistry,
    $fillableGuard,
    new CrudContextFactory(),
), 500)
```

When `valid: false`, pass an `errors` array to `ValidationResult`. `ValidateMiddleware`
throws `ValidationException` → HTTP 422.

---

## AuthorizeMiddleware

```php
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\PermissionEvaluator;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Infrastructure\Security\Policy\CallablePolicy;

->add(new AuthorizeMiddleware(
    new PermissionEvaluator(),
    new PolicyEvaluator(
        new CallablePolicy(
            static fn ($op, $subject, $res): bool => $subject !== null
        ),
        new OperationTypeMapper(),
    ),
), 600)
```

`PermissionEvaluator` checks that the subject's permissions include `{resource}.{operation}`.
`PolicyEvaluator` then runs the registered `PolicyPortInterface` instances.

`AuthorizationException` / `InsufficientPermissionException` → HTTP 403.

---

## AuditMiddleware

```php
use Bamise\Application\Middleware\AuditMiddleware;
use Bamise\Infrastructure\Security\Audit\AuditConfig;
use Bamise\Infrastructure\Security\Audit\PsrAuditLogger;

->add(new AuditMiddleware(
    new PsrAuditLogger($psrLogger, new AuditConfig()),
), 700)
```

`AuditMiddleware` logs only mutating operations (Create, Update, Delete, BulkUpdate, BulkDelete)
and only when they succeed.

---

## SigningMiddleware

For machine-to-machine APIs where you want HMAC-signed requests instead of Bearer tokens:

```php
use Bamise\Application\Middleware\SigningMiddleware;
use Bamise\Infrastructure\Security\Signing\HmacRequestSigner;
use Bamise\Infrastructure\Security\Signing\SigningConfig;
use Bamise\Infrastructure\Cache\InMemoryCache;

$signer = new HmacRequestSigner(
    new InMemoryCache(),
    new SigningConfig(
        secret:           'your-signing-secret',
        timestampHeader:  'X-Bamise-Timestamp',
        nonceHeader:      'X-Bamise-Nonce',
        signatureHeader:  'X-Bamise-Signature',
        maxSkewSeconds:   300,
        nonceTtlSeconds:  600,
    ),
);

->add(new SigningMiddleware($signer), 150)
```

The client must send three headers:

```
X-Bamise-Timestamp: 1716840000
X-Bamise-Nonce: abc123xyz
X-Bamise-Signature: <hmac-sha256>
```

See [Security](security.md) for the canonical string format and a signing example.

---

## Minimal pipeline (no middleware)

For CLI scripts or internal tools that do not need auth or CSRF:

```php
$pipeline = (new PipelineBuilder())->build($terminal);
```

---

## Full recommended pipeline

```php
use Bamise\Application\Middleware\PipelineBuilder;
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Application\Middleware\CsrfMiddleware;
use Bamise\Application\Middleware\SanitizeMiddleware;
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Application\Middleware\AuditMiddleware;

$pipeline = (new PipelineBuilder())
    ->add(new RateLimitMiddleware($rateLimiter),                            100)
    ->add(new AuthenticationMiddleware($authAdapter, $subjectFactory, $ctx), 200)
    ->add(new CsrfMiddleware($csrfService),                                 300)
    ->add(new SanitizeMiddleware($sanitizer, $ctx),                         400)
    ->add(new AuthorizeMiddleware($permEval, $policyEval),                  600)
    ->add(new AuditMiddleware($auditLogger),                                700)
    ->build($terminal);
```

---

## Related

- [Security](security.md) — auth adapters, CSRF, HMAC signing, policies
- [CRUD Operations](crud.md) — what strategies the terminal handler executes
- Architecture: [03-application.md](architecture/03-application.md)
