# SuperKernel Event Dispatcher

`super-kernel/event-dispatcher` implements PSR-14 synchronous event dispatching. It discovers native
`SuperKernel\Attribute\Listener` metadata through Annotator, resolves listener objects
from the shared SuperKernel PSR-11 container, and maps their declared event classes.

## Listener declaration

Implement the shared listener contract, return an indexed list of event class names from `listen()`, handle matching
events through `process(object $event): void`, and mark the class with the Listener Attribute:

```php
use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;

#[Listener(priority: 100)]
final class RecordAuditEntry implements ListenerInterface
{
    public function listen(): array
    {
        return [
            UserRegistered::class,
        ];
    }

    /**
     * @param UserRegistered $event
     */
    public function process(object $event): void
    {
        // Handle the event synchronously.
    }
}
```

`SuperKernel\Contract\ListenerInterface::listen()` must return a list containing only existing event class or interface
names. A listener can declare more than one event. Parent classes and implemented interfaces match child event objects.
Every implementation handles events through `ListenerInterface::process(object $event): void`. The provider invokes a
listener only for a compatible declaration, so a listener declaring one event processes it directly and may document
that concrete type in PHPDoc. When a listener declares more than one event class, its `process()` implementation is
responsible for distinguishing the concrete event, for example with `instanceof` or `match`. The annotated listener
type does not need to be directly instantiable: the DI container may resolve an interface or abstract listener through
a declared factory, provided the resolved object implements `ListenerInterface`.

Higher priorities run first. Equal priorities preserve Annotator discovery order. Listener return values are
ignored.

## Dispatching

`SuperKernel\EventDispatcher\EventDispatcher` implements `Psr\EventDispatcher\EventDispatcherInterface` and composes
the component's distinct `Psr\EventDispatcher\ListenerProviderInterface` implementation. It invokes applicable
listeners synchronously in provider order, returns the same event object, checks `StoppableEventInterface` before the
first listener and after every listener invocation, stops immediately when `isPropagationStopped()` returns `true`,
and lets listener exceptions propagate unchanged.

```php
use Psr\EventDispatcher\EventDispatcherInterface;

$event = $container->get(EventDispatcherInterface::class)->dispatch(new UserRegistered());
```

`SuperKernel\EventDispatcher\Provider\ListenerProviderProvider` supplies `ListenerProviderInterface` through shared DI
`FactoryProvider` metadata. Its `__invoke()` method receives `AnnotationCollectorInterface` through DI parameter
injection, discovers exact `Listener` metadata, reads priority from each trusted `Listener` instance, orders the
listener types, and builds a fresh listener provider. It does not reconstruct or revalidate the Attribute. The listener
provider resolves each annotated listener from the constructor-injected container, validates its shared contract and
event list, and returns it only for declared compatible events. Matching listeners are exposed to PSR-14 as callables
targeting their `process()` method.

`SuperKernel\EventDispatcher\EventDispatcher` declares `#[Provider(EventDispatcherInterface::class)]`. DI maps the
interface directly to this implementation and injects `ListenerProviderInterface` through its constructor. With
SuperKernel DI, interface and concrete-class lookups share the same dispatcher instance. Listener discovery remains
in `ListenerProviderProvider`.

This component remains a pure PSR-14 implementation and does not depend on Symfony. Framework's
`SymfonyEventDispatcher` adapts Symfony Console's dispatcher contract to this PSR boundary and intentionally ignores
Symfony's optional string event name.

## Development

Use a PHP 8.4 Composer in this package's checkout:

```bash
composer install
composer validate --strict
composer test
composer format:check
composer format
```
