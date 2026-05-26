# Events

Bamise fires lifecycle events before and after every mutating CRUD operation. Subscribe to
these events to send notifications, audit mutations, invalidate caches, or trigger background
jobs.

---

## Event types

Six lifecycle events are defined in `Bamise\Contract\Event`:

| Event class | Fired when |
|-------------|-----------|
| `BeforeCreate` | Before an insert is executed |
| `AfterCreate` | After a successful insert |
| `BeforeUpdate` | Before an update is executed |
| `AfterUpdate` | After a successful update |
| `BeforeDelete` | Before a delete is executed |
| `AfterDelete` | After a successful delete |

No events are fired for `Read`, `BulkUpdate`, or `BulkDelete` operations.

All six classes share the same shape:

```php
readonly class AfterCreate implements DomainEventInterface
{
    public function __construct(
        public CrudContext       $context,  // operation, resource, input, subject
        public ?array            $payload,  // result data, or null
    ) {}
}
```

Access the authenticated subject and resource name from the context:

```php
$event->context->resourceName   // e.g. 'users'
$event->context->operation      // OperationType::Create
$event->context->subject        // authenticated object or null
$event->context->inputData      // original request input
$event->payload                 // inserted/updated/deleted row data
```

---

## Wiring the event dispatcher

Pass `SyncEventDispatcher` into `CrudOrchestrator`:

```php
use Bamise\Infrastructure\Event\ListenerRegistry;
use Bamise\Infrastructure\Event\SyncEventDispatcher;
use Bamise\Domain\Event\LifecycleEventFactory;
use Bamise\Application\Handler\CrudOrchestrator;
use Bamise\Application\Handler\StrategyDispatchHandler;
use Bamise\Application\Strategy\OperationStrategyFactory;

$listenerRegistry = new ListenerRegistry();
$eventDispatcher  = new SyncEventDispatcher($listenerRegistry);

$terminal = new CrudOrchestrator(
    $eventDispatcher,
    new LifecycleEventFactory(),
    new StrategyDispatchHandler(
        new OperationStrategyFactory($repositoryResolver, $resourceRegistry, $fillableGuard)
    ),
);
```

Register listeners on `$listenerRegistry` or `$eventDispatcher` before the first request.

---

## Subscribing with a callable

```php
use Bamise\Contract\Event\AfterCreate;

$eventDispatcher->subscribe(
    AfterCreate::class,
    function (AfterCreate $event): void {
        error_log(sprintf(
            'Created %s: %s',
            $event->context->resourceName,
            json_encode($event->payload),
        ));
    },
);
```

`subscribe()` registers a synchronous listener. The callable receives the event object.

**Priority** — higher numbers run first (opposite of middleware):

```php
$eventDispatcher->subscribe(AfterCreate::class, $listenerA, priority: 10);
$eventDispatcher->subscribe(AfterCreate::class, $listenerB, priority: 5);
// $listenerA runs before $listenerB
```

**Stop propagation** — return `false` from a listener to prevent subsequent listeners
from being called:

```php
$eventDispatcher->subscribe(BeforeCreate::class, function ($event): bool|null {
    if ($event->context->resourceName === 'blocked') {
        return false; // stops chain
    }
    return null;
});
```

---

## Subscribing with a class (EventSubscriberInterface)

Create a subscriber class and load it with `SubscriberLoader`:

```php
<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\AfterUpdate;
use Bamise\Contract\Event\AfterDelete;
use Bamise\Infrastructure\Event\EventSubscriberInterface;

final class AuditSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [
            AfterCreate::class => ['onMutate', 10],
            AfterUpdate::class => 'onMutate',
            AfterDelete::class => 'onMutate',
        ];
    }

    public function onMutate(object $event): void
    {
        $resource = $event->context->resourceName;
        $op       = $event->context->operation->value;
        $actor    = $event->context->subject?->id ?? 'anonymous';
        error_log("[AUDIT] {$actor} performed {$op} on {$resource}");
    }
}
```

Load into the dispatcher:

```php
use Bamise\Infrastructure\Event\SubscriberLoader;

$loader = new SubscriberLoader();
$loader->load($eventDispatcher, new AuditSubscriber());
```

`getSubscribedEvents()` returns a map of event class → method name or `[method, priority]`.

---

## Practical examples

### Send a welcome email after user creation

```php
use Bamise\Contract\Event\AfterCreate;

$eventDispatcher->subscribe(AfterCreate::class, function (AfterCreate $event) use ($mailer): void {
    if ($event->context->resourceName !== 'users') {
        return;
    }

    $email = $event->payload['email'] ?? null;
    $name  = $event->payload['name']  ?? 'there';

    if ($email !== null) {
        $mailer->send($email, "Welcome {$name}!", 'welcome.html');
    }
});
```

### Invalidate a cache after any mutation

```php
use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\AfterUpdate;
use Bamise\Contract\Event\AfterDelete;

foreach ([AfterCreate::class, AfterUpdate::class, AfterDelete::class] as $eventClass) {
    $eventDispatcher->subscribe($eventClass, function (object $event) use ($cache): void {
        $cache->delete('users:list');
    });
}
```

### Log all lifecycle events (built-in subscriber)

Bamise ships an example subscriber `LogLifecycleSubscriber` that logs every lifecycle event:

```php
use Bamise\Infrastructure\Event\Examples\LogLifecycleSubscriber;
use Bamise\Infrastructure\Event\SubscriberLoader;
use Psr\Log\NullLogger;

$loader = new SubscriberLoader();
$loader->load($eventDispatcher, new LogLifecycleSubscriber(new NullLogger()));
```

Replace `NullLogger` with any PSR-3 logger.

---

## Async listeners

Mark a listener as async so it is pushed to a queue instead of executed inline:

```php
use Bamise\Contract\Event\AfterCreate;

$eventDispatcher->subscribeAsync(
    AfterCreate::class,
    function (AfterCreate $event): void {
        // This runs in a queue worker, not during the HTTP request
        $emailService->sendWelcome($event->payload['email']);
    },
);
```

Async dispatch requires a `QueuePortInterface` implementation passed to `SyncEventDispatcher`:

```php
$eventDispatcher = new SyncEventDispatcher($listenerRegistry, $queue);
```

Without a queue implementation, `subscribeAsync` throws `RuntimeException` at dispatch time.
Implement `Bamise\Contract\QueuePortInterface` to integrate with your queue backend (Redis,
RabbitMQ, etc.).

---

## All six events

```php
use Bamise\Contract\Event\BeforeCreate;
use Bamise\Contract\Event\AfterCreate;
use Bamise\Contract\Event\BeforeUpdate;
use Bamise\Contract\Event\AfterUpdate;
use Bamise\Contract\Event\BeforeDelete;
use Bamise\Contract\Event\AfterDelete;

$eventDispatcher->subscribe(BeforeCreate::class, function (BeforeCreate $event): void {
    // $event->context->inputData — the data ABOUT to be inserted
});

$eventDispatcher->subscribe(AfterCreate::class, function (AfterCreate $event): void {
    // $event->payload — inserted row including generated primary key
});

$eventDispatcher->subscribe(BeforeUpdate::class, function (BeforeUpdate $event): void {
    // $event->context->inputData — the update payload
});

$eventDispatcher->subscribe(AfterUpdate::class, function (AfterUpdate $event): void {
    // $event->payload — updated row
});

$eventDispatcher->subscribe(BeforeDelete::class, function (BeforeDelete $event): void {
    // $event->context->inputData — contains 'id' of the record being deleted
});

$eventDispatcher->subscribe(AfterDelete::class, function (AfterDelete $event): void {
    // $event->payload — {'id': <deleted-id>}
});
```

---

## Related

- [Middleware](middleware.md) — for request-scoped transformations before the strategy runs
- Architecture: [09-events.md](architecture/09-events.md)
