# Security Policy

## Supported Versions

| Version | Supported |
|---|---|
| 0.1.x (latest) | Yes |
| < 0.1.0 | No |

---

## Reporting a Vulnerability

**Do not open a public GitHub issue for a security vulnerability.**

Please report security issues by emailing:

**lungu.sports.entertainment@gmail.com**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Any suggested mitigation or fix

You will receive an acknowledgement within **48 hours** and a resolution timeline within **7 days**.

---

## Security Considerations for Integrators

### SQL Injection

All queries are compiled through `SqlCompiler`, which uses PDO parameterised statements exclusively.
Never concatenate user input into SQL strings; use the `FieldBag` / `CrudRequestInterface` flow so the
`PdoRepository` handles binding automatically.

### Cross-Site Scripting (XSS)

`HtmlSanitizer` strips HTML tags. Register `SanitizeMiddleware` in your pipeline to sanitise all inbound
string fields before they reach the domain layer.

### CSRF

`CsrfGuard` issues per-session tokens backed by `CacheInterface`. Wire it as a pre-handler in your HTTP
adapter; tokens expire after the configured TTL.

### Rate Limiting

`CacheRateLimiter` uses a sliding-window counter. Register `RateLimitMiddleware` and pass a cache
implementation with a shared backend (Redis, Memcached) in production. The built-in `InMemoryCache` is
process-local and not suitable for multi-process deployments.

### Authentication

`BearerTokenAuthAdapter` and `SessionAuthAdapter` are lightweight stubs. For production, replace or
extend them — do not store plaintext secrets in session fields. `JwtAuthAdapter` requires
`firebase/php-jwt`; always validate `alg`, `exp`, and `iss` claims.

### Request Signing

`HmacRequestSigner` uses HMAC-SHA256. Store the signing secret in an environment variable; never commit
it to version control. Use `hash_equals` for comparison (already done internally) to prevent
timing-attack leaks.

### Audit Logging

`PsrAuditLogger` redacts configured field names from log context. Ensure `password`, `token`, and other
credential fields are in the redaction list for every environment.

---

## Disclosure Policy

Once a fix is released, the vulnerability will be disclosed publicly in the `CHANGELOG.md` and a GitHub
Security Advisory will be published.
