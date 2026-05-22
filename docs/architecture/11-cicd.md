# Module 11 — CI/CD

GitHub Actions pipelines, static analysis, code style, and mutation testing configuration for the Bamise library.

## Workflows

### `.github/workflows/ci.yml` — main pipeline (blocks PRs)

Three parallel jobs run on every push to `main`/`master` and on every pull request:

| Job | Tool | What it checks |
|-----|------|----------------|
| `tests` | PHPUnit 11 | 232 tests, coverage report via pcov → Codecov |
| `static-analysis` | PHPStan 2 (level 6) | Type safety, dead code, undefined properties |
| `code-style` | PHP CS Fixer 3 | PSR-12 / PER-CS 2.0 code style |

`concurrency` cancels in-progress runs for the same ref to avoid queuing duplicates on force-push.

### `.github/workflows/mutation.yml` — mutation testing (non-blocking)

Runs `infection/infection` only on push to `main` and via `workflow_dispatch`. Kept separate to avoid slowing down PR feedback loops.

Thresholds: `--min-msi=70 --min-covered-msi=80`.

## Local commands

```bash
composer test           # PHPUnit, no coverage (fastest)
composer test-coverage  # PHPUnit + coverage.xml + coverage/
composer analyse        # PHPStan
composer cs-check       # PHP CS Fixer (dry-run, exit 1 on violations)
composer cs-fix         # PHP CS Fixer (auto-fix)
composer mutation       # Infection mutation suite
composer ci             # test + analyse + cs-check (mirrors CI pipeline)
```

## Tool configuration

### PHPStan (`phpstan.neon`)

- **Level 6** — catches undefined variables, wrong argument types, unreachable code
- `checkDynamicProperties: false` — `EventPayloadEncoder` accesses `->context`/`->payload` after `property_exists()` guards; PHPStan cannot trace this without the flag
- `treatPhpDocTypesAsCertain: false` — interface/abstract type annotations are hints, not assertions

### PHP CS Fixer (`.php-cs-fixer.dist.php`)

- Ruleset: `@PER-CS2.0` + `@PHP84Migration`
- `declare(strict_types=1)` enforced on all files
- Alphabetical imports, no unused imports, trailing commas in multi-line constructs
- Cache stored in `.php-cs-fixer.cache` (excluded from git)

### Infection (`infection.json5`)

- Covers `src/` (excludes `Infrastructure/Event/Examples`)
- Default mutator set (`@default`)
- 4 parallel threads
- Logs written to `.infection/` (excluded from git)
- Badge endpoint wired via `INFECTION_BADGE_API_KEY` secret

### PHPUnit (`phpunit.xml.dist`)

- Two named suites: `Unit` (`tests/Unit`) and `Integration` (`tests/Integration`)
- Coverage report: Clover XML → `coverage.xml`, HTML → `coverage/`
- `failOnRisky="true"` — risky tests (no assertions, output side-effects) fail the build
- Examples directory (`Infrastructure/Event/Examples`) excluded from coverage source

## Secrets required

| Secret | Used by |
|--------|---------|
| `CODECOV_TOKEN` | `ci.yml` — coverage upload (optional; upload still succeeds without it on public repos) |
| `INFECTION_BADGE_API_KEY` | `mutation.yml` — badge endpoint on infection.codes |

## Cache strategy

All three CI jobs share a Composer vendor cache keyed on `composer.json` hash. PHPStan and PHP CS Fixer caches are stored separately so a dependency change doesn't invalidate analysis caches unnecessarily.
