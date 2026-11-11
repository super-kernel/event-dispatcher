<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Event Dispatcher

`super-kernel/event-dispatcher` is the PSR-14 event dispatcher of SuperKernel: it finds the classes declared with `#[Listener]`, creates them through the container, and calls the ones that match an event synchronously, in priority order.

## Installation

```bash
composer require super-kernel/event-dispatcher
```

The package provides `psr/event-dispatcher-implementation` 1.0. It creates listeners through a PSR-11 container, `super-kernel/di` in a SuperKernel application, and finds them in the metadata that `skernel build` generates, so a project needs SKERNEL to build it.

## What it provides

- `Psr\EventDispatcher\EventDispatcherInterface`, which dispatches any event object and returns it.
- Listeners declared with `#[Listener]` and `ListenerInterface`, ordered by priority.
- Matching by class: a listener of a class or interface also receives its subclasses and implementations.
- Stopping propagation through PSR-14's `StoppableEventInterface`.
- `Psr\EventDispatcher\ListenerProviderInterface`, which lists the listeners of an event.

It does not:

- dispatch asynchronously or defer events;
- collect listener return values;
- register or remove listeners at run time;
- adapt Symfony's event dispatcher; `super-kernel/framework` does.

## Quick start

Declare an event. Save it as `src/Account/UserRegistered.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Account;

final readonly class UserRegistered
{
    public function __construct(public string $email)
    {
    }
}
```

Declare a listener of the event. Save it as `src/Account/SendWelcomeMail.php`:

```php
<?php

declare(strict_types=1);

namespace Src\Account;

use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;

#[Listener]
final readonly class SendWelcomeMail implements ListenerInterface
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
        echo "Welcome mail sent to {$event->email}", PHP_EOL;
    }
}
```

Dispatch the event from a script. Save it as `bin/register.php`:

```php
<?php

declare(strict_types=1);

use Psr\EventDispatcher\EventDispatcherInterface;
use Src\Account\UserRegistered;
use SuperKernel\Context\ApplicationContext;

require dirname(__DIR__) . '/vendor/autoload.php';

$events = ApplicationContext::getContainer()->get(EventDispatcherInterface::class);

$events->dispatch(new UserRegistered('ada@example.com'));
```

Build the project and run the script:

```bash
skernel build
php bin/register.php
```

It prints `Welcome mail sent to ada@example.com`.

## Public surface

| Name | Kind | Purpose |
| --- | --- | --- |
| `SuperKernel\Attribute\Listener` | Attribute | Registers the annotated class as a listener; `priority` orders it, higher first. |
| `SuperKernel\Contract\ListenerInterface` | Interface | Implemented by a listener: `listen()` names its events, `process()` handles one. |
| `Psr\EventDispatcher\EventDispatcherInterface` | Interface | Provided by the container; dispatches an event to its listeners. |
| `Psr\EventDispatcher\ListenerProviderInterface` | Interface | Provided by the container; returns the listeners of an event in order. |

## Behavior to know

- Listeners run synchronously, higher priorities first and equal priorities in the order the build found them; `dispatch()` returns the same event object.
- A listener's exception propagates unchanged out of `dispatch()`, and later listeners do not run.
- Every listener is created when the dispatcher is first needed. A listener that does not implement `ListenerInterface`, or whose `listen()` does not return a list of existing class or interface names, raises `InvalidArgumentException` then, so no event can be dispatched and `skernel build` fails.
- Listeners are found in the build metadata: run `skernel build` again after adding, removing, or changing a listener, or start with `skernel run`, which builds first.

## Documentation

The developer documentation describes declaring listeners, matching several events, stopping propagation, the listener provider, and the error messages: [SuperKernel Event Dispatcher](https://github.com/super-kernel/.github/blob/main/docs/components/event-dispatcher.md).

## License

Released under the MIT License. See [LICENSE](LICENSE).
