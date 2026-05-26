# Security

Bamise provides layered security through middleware. This page covers authentication,
authorization, CSRF protection, request signing, rate limiting, and HTML sanitization.

---

## Authentication

Authentication is handled by `AuthenticationMiddleware` using an `AuthPortInterface` adapter.
The adapter is called for every request and sets `CrudContext::subject` to an authenticated
object (or `null` if unauthenticated).

### BearerTokenAuthAdapter

The built-in adapter reads `Authorization: Bearer {payload}` and parses the payload as:

```
{subject_id}|{comma-separated-roles}|{comma-separated-permissions}
```

Examples:

```
Authorization: Bearer 1||users.read
Authorization: Bearer 42|admin|users.read,users.create,users.update,users.delete
Authorization: Bearer user-uuid-here|editor|posts.read,posts.create
```

Fields:
- `subject_id` — integer or string; becomes `CrudContext::subject->id`
- `roles` — optional comma-separated strings (empty = no roles)
- `permissions` — optional comma-separated `{resource}.{operation}` strings

```php
use Bamise\Application\Context\CrudContextFactory;
use Bamise\Application\Context\SubjectFactory;
use Bamise\Application\Middleware\AuthenticationMiddleware;
use Bamise\Infrastructure\Security\Auth\BearerTokenAuthAdapter;

->add(new AuthenticationMiddleware(
    new BearerTokenAuthAdapter(),
    new SubjectFactory(),
    new CrudContextFactory(),
), 200)
```

### SessionAuthAdapter

Reads the subject from `$_SESSION`. Useful for server-rendered applications:

```php
use Bamise\Infrastructure\Security\Auth\SessionAuthAdapter;

->add(new AuthenticationMiddleware(
    new SessionAuthAdapter(),
    new SubjectFactory(),
    new CrudContextFactory(),
), 200)
```

### JwtAuthAdapter

Verifies signed JWT Bearer tokens. Requires `firebase/php-jwt`:

```bash
composer require firebase/php-jwt:^6.10
```

```php
use Bamise\Infrastructure\Security\Auth\JwtAuthAdapter;

->add(new AuthenticationMiddleware(
    new JwtAuthAdapter(secret: getenv('BAMISE_SIGNING_SECRET')),
    new SubjectFactory(),
    new CrudContextFactory(),
), 200)
```

The JWT must contain a `sub` claim (or configure a different claim via the second constructor
argument). No roles or permissions are extracted from the token — use policies to enforce
access control when using JWTs.

---

## Authorization

`AuthorizeMiddleware` runs after authentication and enforces two things:

1. **Permission check** (`PermissionEvaluator`) — the subject must have the permission
   string `{resource}.{operation}` in their permissions list.
2. **Policy check** (`PolicyEvaluator`) — all registered `PolicyPortInterface` instances
   must return `true`.

```php
use Bamise\Application\Middleware\AuthorizeMiddleware;
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Domain\Service\PermissionEvaluator;
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

### Permission strings

| Operation | Permission string |
|-----------|------------------|
| GET / list | `{resource}.read` |
| POST / create | `{resource}.create` |
| PUT or PATCH / update | `{resource}.update` |
| DELETE | `{resource}.delete` |
| Bulk update | `{resource}.bulk_update` |
| Bulk delete | `{resource}.bulk_delete` |

`AuthorizationException` / `InsufficientPermissionException` → HTTP 403.

---

## Policies

### CallablePolicy

The simplest policy — supply a callable predicate:

```php
use Bamise\Infrastructure\Security\Policy\CallablePolicy;

// Allow only authenticated subjects
new CallablePolicy(
    static fn ($op, $subject, $res): bool => $subject !== null
)

// Allow only subjects with an 'admin' role
new CallablePolicy(function ($op, $subject, $resource) {
    if ($subject === null) { return false; }
    return in_array('admin', (array) ($subject->roles ?? []), true);
})
```

Signature: `callable(OperationType $op, ?object $subject, string $resource): bool`

### PolicyChain

Combine multiple policies with AND semantics (all must return `true`):

```php
use Bamise\Infrastructure\Security\Policy\PolicyChain;

new PolicyChain(
    new CallablePolicy(fn ($op, $sub, $res) => $sub !== null),
    new CallablePolicy(fn ($op, $sub, $res) => $res !== 'admin_logs'),
)
```

### Resource-scoped policy classes

Register policy classes per resource in `ResourceDefinitionInterface::policyClasses()`.
Each class must implement `PolicyInterface`:

```php
// src/Policy/UserPolicy.php
use Bamise\Infrastructure\Security\Policy\PolicyInterface;

final class UserPolicy implements PolicyInterface
{
    public function allows(object $subject, string $action, string $resource, mixed $target = null): bool
    {
        // Only allow users to update their own record
        if ($action === 'update') {
            return isset($subject->id) && $target !== null
                && (string) $subject->id === (string) ($target['id'] ?? '');
        }

        return true;
    }
}
```

Reference the class in your `ResourceDefinitionInterface`:

```php
public function policyClasses(): array
{
    return [UserPolicy::class];
}
```

Then use `ClassPolicyAdapter` in the `PolicyEvaluator`:

```php
use Bamise\Domain\Policy\PolicyEvaluator;
use Bamise\Domain\Service\OperationTypeMapper;
use Bamise\Infrastructure\Security\Policy\ClassPolicyAdapter;

new PolicyEvaluator(
    new ClassPolicyAdapter($userDefinition->policyClasses()),
    new OperationTypeMapper(),
)
```

---

## CSRF protection

CSRF protection is required for HTML form submissions (not for API Bearer-token requests).

### How it works

1. **Generate a token** — call `SessionCsrfService::generateForSession($sessionId)` when
   rendering the form. Store the token in the cache keyed to the session ID.
2. **Embed the token** — add `_session_id` and `_csrf` as hidden form fields.
3. **Validate on submit** — `CsrfMiddleware` reads both fields from the request body and
   verifies the token. Tokens are single-use (deleted after successful verification).

```php
use Bamise\Infrastructure\Security\Csrf\CsrfConfig;
use Bamise\Infrastructure\Security\Csrf\CsrfTokenGenerator;
use Bamise\Infrastructure\Security\Csrf\SessionCsrfService;
use Bamise\Infrastructure\Cache\InMemoryCache;

$csrfService = new SessionCsrfService(
    new InMemoryCache(),
    new CsrfTokenGenerator(),
    new CsrfConfig(),   // defaults: fieldName='_csrf', sessionField='_session_id', ttl=3600
);
```

### Form template

```php
session_start();
$sessionId = session_id();
$csrfToken = $csrfService->generateForSession($sessionId);
$sessionEnc = htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8');
$tokenEnc   = htmlspecialchars($csrfToken,  ENT_QUOTES, 'UTF-8');
```

```html
<form method="POST" action="/users">
    <input type="hidden" name="_session_id" value="<?= $sessionEnc ?>">
    <input type="hidden" name="_csrf"       value="<?= $tokenEnc ?>">
    <input type="text"  name="name"  required>
    <input type="email" name="email" required>
    <button type="submit">Create</button>
</form>
```

`CsrfException` → HTTP 403.

### Customising CSRF config

```php
new CsrfConfig(
    fieldName:        '_csrf',       // input name in the form
    tokenLength:      32,            // bytes of randomness
    ttlSeconds:       3600,          // token lifetime in seconds
    sessionField:     '_session_id', // input name for session ID
    defaultSessionId: 'default',     // fallback when generateToken() (no session) is used
)
```

---

## HMAC request signing

For machine-to-machine integrations where you cannot use Bearer tokens, use
`SigningMiddleware` with `HmacRequestSigner`.

### Server-side setup

```php
use Bamise\Application\Middleware\SigningMiddleware;
use Bamise\Infrastructure\Security\Signing\HmacRequestSigner;
use Bamise\Infrastructure\Security\Signing\SigningConfig;
use Bamise\Infrastructure\Cache\InMemoryCache;

$signer = new HmacRequestSigner(
    new InMemoryCache(),
    new SigningConfig(
        secret:          'your-shared-secret',
        maxSkewSeconds:  300,   // allow ±5 minutes clock drift
        nonceTtlSeconds: 600,   // replay protection window
    ),
);

->add(new SigningMiddleware($signer), 150)
```

### Client-side signing

The canonical string is:

```
METHOD\n
PATH\n
TIMESTAMP\n
NONCE\n
SHA256(json_encode(body))
```

PHP example:

```php
$method    = 'POST';
$path      = '/users';
$timestamp = (string) time();
$nonce     = bin2hex(random_bytes(16));
$body      = ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'];
$bodyHash  = hash('sha256', json_encode($body, JSON_THROW_ON_ERROR));
$secret    = 'your-shared-secret';

$canonical = implode("\n", [$method, $path, $timestamp, $nonce, $bodyHash]);
$signature = hash_hmac('sha256', $canonical, $secret);

// Send as headers:
// X-Bamise-Timestamp: {$timestamp}
// X-Bamise-Nonce: {$nonce}
// X-Bamise-Signature: {$signature}
```

`AuthorizationException` (invalid/missing signature) → HTTP 403.

---

## Rate limiting

### CacheRateLimiter (development)

`InMemoryCache` is per-process. Use only for single-worker development servers.

```php
use Bamise\Application\Middleware\RateLimitMiddleware;
use Bamise\Infrastructure\Security\RateLimit\CacheRateLimiter;
use Bamise\Infrastructure\Security\RateLimit\RateLimitConfig;
use Bamise\Infrastructure\Cache\InMemoryCache;

->add(new RateLimitMiddleware(
    new CacheRateLimiter(
        new InMemoryCache(),
        new RateLimitConfig(maxAttempts: 60, windowSeconds: 60),
    )
), 100)
```

### RedisRateLimiter (production)

For multi-worker PHP-FPM or multi-container environments, use the Redis-backed limiter
so the counter is shared:

```php
use Bamise\Infrastructure\Security\RateLimit\RedisRateLimiter;

->add(new RateLimitMiddleware(
    new RedisRateLimiter(
        $redisClient,   // implements RedisClientInterface
        new RateLimitConfig(maxAttempts: 100, windowSeconds: 60),
    )
), 100)
```

`RateLimitException` → HTTP 429.

---

## HTML sanitization

`SanitizeMiddleware` applies `HtmlSanitizer` to all string fields in `inputData` before
they reach the strategy.

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

---

## SecurityFactory convenience wrapper

`SecurityFactory` is a factory that creates all security port implementations from one
place. Use it in tests or simple bootstrap files:

```php
use Bamise\Infrastructure\Cache\InMemoryCache;
use Bamise\Infrastructure\Security\SecurityFactory;
use Psr\Log\NullLogger;

$security = new SecurityFactory(
    cache:  new InMemoryCache(),
    logger: new NullLogger(),
);

$csrfService = $security->csrf();          // SessionCsrfService
$sanitizer   = $security->sanitizer();     // HtmlSanitizer
$rateLimiter = $security->rateLimiter();   // CacheRateLimiter
$authAdapter = $security->bearerAuth();    // BearerTokenAuthAdapter
$signer      = $security->requestSigner(); // HmacRequestSigner
$auditLogger = $security->auditLogger();   // PsrAuditLogger
```

---

## Related

- [Middleware](middleware.md) — pipeline setup and custom middleware
- [First Project](first-project.md) — complete wiring example
- Architecture: [08-security.md](architecture/08-security.md)
