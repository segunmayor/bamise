# Module 12 — Composer / Packagist Setup

## Purpose

Makes Bamise a fully publishable Composer package:
discoverable on Packagist, self-describing via metadata, and usable by downstream projects with a single
`composer require bamise/bamise`.

---

## Package Identity

| Field | Value |
|---|---|
| `name` | `bamise/bamise` |
| `type` | `library` |
| `license` | MIT |
| `homepage` | https://github.com/bamise/bamise |

Keywords: `crud`, `repository`, `ddd`, `hexagonal`, `security`, `psr`, `event`, `php8`, `enterprise`

---

## Versioning Strategy

Bamise follows [Semantic Versioning](https://semver.org/):

- **PATCH** (`0.1.x`) — bug fixes, no API changes
- **MINOR** (`0.x.0`) — backwards-compatible new features
- **MAJOR** (`x.0.0`) — breaking interface or behaviour changes

### Branch Alias

```json
"extra": {
    "branch-alias": {
        "dev-master": "0.1.x-dev",
        "dev-main":   "0.1.x-dev"
    }
}
```

This allows downstream projects pinned to `dev-main` to resolve correctly against stability constraints.

### Tagging

```bash
git tag -a v0.1.0 -m "Initial release"
git push origin v0.1.0
```

Packagist auto-discovers tags via GitHub webhook.

---

## Autoloading

```json
"autoload":     { "psr-4": { "Bamise\\":       "src/"   } },
"autoload-dev": { "psr-4": { "Bamise\\Tests\\": "tests/" } }
```

**One class per file** — PSR-4 requires the file name to match the class name exactly. Secondary class
definitions in the same file are not autoloaded.

---

## Production Dependencies

| Package | Constraint | Reason |
|---|---|---|
| `php` | `^8.4` | Readonly properties, enums, named arguments, fibers |
| `psr/container` | `^2.0` | PSR-11 container interface |
| `psr/log` | `^3.0` | PSR-3 logger interface for `PsrAuditLogger` |

### Optional (suggest)

| Package | Constraint | Feature |
|---|---|---|
| `firebase/php-jwt` | `^6.10` | `JwtAuthAdapter` (HS256 bearer validation) |

---

## Development Dependencies

| Package | Constraint | Role |
|---|---|---|
| `phpunit/phpunit` | `^11` | Unit and integration tests |
| `phpstan/phpstan` | `^2.0` | Static analysis (level 6) |
| `friendsofphp/php-cs-fixer` | `^3.0` | Code style (`@PER-CS2.0` + `@PHP84Migration`) |
| `infection/infection` | `^0.29` | Mutation testing (minMsi 70%) |

---

## Composer Scripts

| Command | Script | Description |
|---|---|---|
| `composer test` | `phpunit --no-coverage` | Fast test run |
| `composer test-coverage` | `phpunit --coverage-clover coverage.xml` | Coverage report |
| `composer analyse` | `phpstan analyse --memory-limit=512M` | Static analysis |
| `composer cs-check` | `php-cs-fixer check --ansi --diff` | Style check (dry-run) |
| `composer cs-fix` | `php-cs-fixer fix --ansi` | Auto-fix style |
| `composer mutation` | `infection --min-msi=70 --min-covered-msi=80 --threads=4` | Mutation testing |
| `composer ci` | `@test @analyse @cs-check` | Full local CI |

---

## Support and Community

- **Issues**: https://github.com/bamise/bamise/issues
- **Contributing**: [.github/CONTRIBUTING.md](../../.github/CONTRIBUTING.md)
- **Security**: [.github/SECURITY.md](../../.github/SECURITY.md)
- **Changelog**: [CHANGELOG.md](../../CHANGELOG.md)

---

## Publishing Checklist

- [ ] `composer validate --strict` passes
- [ ] All CI checks green on `main`
- [ ] `CHANGELOG.md` entry for the release version
- [ ] Tag pushed: `git tag -a v0.1.0 -m "Initial release" && git push origin v0.1.0`
- [ ] Packagist webhook configured (auto-triggered on GitHub tag push)
- [ ] `INFECTION_BADGE_API_KEY` secret set in GitHub repository settings

---

## Related Modules

- [11-cicd.md](11-cicd.md) — GitHub Actions workflows that exercise `composer ci` and `composer mutation`
- [01-contracts.md](01-contracts.md) — public API surface that forms the stable `0.1.x` contract
