<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Event Dispatcher

`super-kernel/event-dispatcher` implements PSR-14 synchronous event dispatching and provides `psr/event-dispatcher-implementation` 1.0. It discovers classes marked with `SuperKernel\Attribute\Listener`, resolves them from the shared SuperKernel PSR-11 container, and maps their declared event classes. It does not depend on Symfony.

## Listener declaration

Implement the shared listener contract, return an indexed list of event class names from `listen()`, handle matching events through `process(object $event): void`, and mark the class with the Listener Attribute:

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

`SuperKernel\Contract\ListenerInterface::listen()` must return a list containing only existing event class or interface names. A listener can declare more than one event. Parent classes and implemented interfaces match child event objects. Every implementation handles events through `ListenerInterface::process(object $event): void`. The provider invokes a listener only for a compatible declaration, so a listener declaring one event processes it directly and may document that concrete type in PHPDoc. When a listener declares more than one event class, its `process()` implementation is responsible for distinguishing the concrete event, for example with `instanceof` or `match`. The annotated listener type does not need to be directly instantiable: the DI container may resolve an interface or abstract listener through a declared factory, provided the resolved object implements `ListenerInterface`.

Higher priorities run first. Equal priorities preserve discovery order. Listener return values are ignored.

Listeners are resolved from the container when the listener provider is created. A resolved object that does not implement `ListenerInterface`, or an invalid `listen()` result, raises `InvalidArgumentException`.

## Dispatching

Inject `Psr\EventDispatcher\EventDispatcherInterface`:

```php
use Psr\EventDispatcher\EventDispatcherInterface;

$event = $container->get(EventDispatcherInterface::class)->dispatch(new UserRegistered());
```

The dispatcher invokes applicable listeners synchronously in priority order, returns the same event object, checks `StoppableEventInterface` before the first listener and after every listener invocation, stops immediately when `isPropagationStopped()` returns `true`, and lets listener exceptions propagate unchanged.

`Psr\EventDispatcher\ListenerProviderInterface` is also available from the container. For each event it returns the matching listeners as `[$listener, 'process']` callables in priority order. The dispatcher's interface and concrete-class lookups share one instance.
