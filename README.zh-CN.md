<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Event Dispatcher

`super-kernel/event-dispatcher` 实现 PSR-14 同步事件分发，并提供 `psr/event-dispatcher-implementation` 1.0。它发现带有 `SuperKernel\Attribute\Listener` 的类，从共享的 SuperKernel PSR-11 容器中解析它们，并映射其声明的事件类。它不依赖 Symfony。

## 声明监听器

实现共享的监听器契约：在 `listen()` 中返回事件类名的索引列表，通过 `process(object $event): void` 处理匹配的事件，并给类加上 Listener 注解：

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

`SuperKernel\Contract\ListenerInterface::listen()` 返回的列表只能包含已存在的事件类名或接口名。一个监听器可以声明多个事件。父类和已实现的接口会匹配子类事件对象。所有实现都通过 `ListenerInterface::process(object $event): void` 处理事件。提供者只会为兼容的声明调用监听器，所以只声明一个事件的监听器可以直接处理它，并在 PHPDoc 中注明具体类型。当监听器声明了多个事件类时，由它的 `process()` 实现自行区分具体事件，例如使用 `instanceof` 或 `match`。带注解的监听器类型不必能直接实例化：只要解析出的对象实现了 `ListenerInterface`，DI 容器可以通过声明的工厂解析接口或抽象类形式的监听器。

优先级高的先执行。优先级相同时保持发现顺序。监听器的返回值会被忽略。

监听器在创建监听器提供者时从容器中解析。解析出的对象未实现 `ListenerInterface`，或 `listen()` 的结果无效时，会抛出 `InvalidArgumentException`。

## 分发

注入 `Psr\EventDispatcher\EventDispatcherInterface`：

```php
use Psr\EventDispatcher\EventDispatcherInterface;

$event = $container->get(EventDispatcherInterface::class)->dispatch(new UserRegistered());
```

分发器按优先级顺序同步调用适用的监听器，返回同一个事件对象；在第一个监听器之前以及每个监听器调用之后检查 `StoppableEventInterface`，`isPropagationStopped()` 返回 `true` 时立即停止，并让监听器抛出的异常原样传播。

容器同样提供 `Psr\EventDispatcher\ListenerProviderInterface`。对每个事件，它按优先级顺序以 `[$listener, 'process']` 可调用形式返回匹配的监听器。分发器的接口查找和具体类查找共享同一个实例。
