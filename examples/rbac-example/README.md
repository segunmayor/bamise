# rbac-example

Role-based access control with a custom `ArticlePolicy`. Three roles:

| Role | Can do |
|------|--------|
| `admin` | Everything |
| `author` | Read, create, update/delete **own** articles |
| `viewer` | Read only |

## Setup

```bash
cd examples/rbac-example
composer install
php -S localhost:8080 -t public
```

## Bearer token format

```
Authorization: Bearer {id}|{roles}|{permissions}
```

## Try it

```bash
# Admin creates an article (author_id=1 = the admin)
curl -s -X POST http://localhost:8080/articles \
  -d "author_id=1&title=Admin+Post&body=Written+by+admin" \
  -H "Authorization: Bearer 1|admin|articles.create"

# Author creates their own article (author_id=2 = the author)
curl -s -X POST http://localhost:8080/articles \
  -d "author_id=2&title=Author+Post&body=Written+by+author" \
  -H "Authorization: Bearer 2|author|articles.create"

# Viewer reads all articles
curl -s http://localhost:8080/articles \
  -H "Authorization: Bearer 3|viewer|articles.read"

# Viewer attempts to create → HTTP 403 (policy denies)
curl -s -X POST http://localhost:8080/articles \
  -d "author_id=3&title=Viewer+Post&body=Should+fail" \
  -H "Authorization: Bearer 3|viewer|articles.create"

# Author updates their own article (id=2 belongs to author_id=2)
curl -s -X PUT http://localhost:8080/articles \
  -d "id=2&title=Updated+Title" \
  -H "Authorization: Bearer 2|author|articles.update"

# Author tries to update admin's article (id=1 belongs to author_id=1) → HTTP 403
curl -s -X PUT http://localhost:8080/articles \
  -d "id=1&title=Hack+Attempt" \
  -H "Authorization: Bearer 2|author|articles.update"
```

## How it works

`ArticlePolicy` (in `src/Policy/ArticlePolicy.php`) implements
`Bamise\Infrastructure\Security\Policy\PolicyInterface`:

```php
public function allows(object $subject, string $action, string $resource, mixed $target = null): bool
```

`$target` receives the row being updated/deleted (when the record is fetched before the
operation). `$subject->roles` contains the roles from the Bearer token.

The policy is registered in `ArticleDefinition::policyClasses()` and loaded via
`ClassPolicyAdapter` in the pipeline.

## What's demonstrated

- `PolicyInterface` — resource-scoped policy with role awareness
- `ClassPolicyAdapter` — loads policy classes from `policyClasses()`
- `PolicyEvaluator` — evaluates policies alongside permission checks
- Role-based decisions: admin, author, viewer
- Row-level ownership check on update/delete
