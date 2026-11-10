<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Event Dispatcher

`super-kernel/event-dispatcher` 是 SuperKernel 的 PSR-14 事件分发器：它找到用 `#[Listener]` 声明的类，通过容器创建它们，并按优先级顺序同步调用与事件匹配的监听器。

## 安装

```bash
composer require super-kernel/event-dispatcher
```

本包提供 `psr/event-dispatcher-implementation` 1.0。它通过 PSR-11 容器创建监听器（在 SuperKernel 应用中是 `super-kernel/di`），并从 `skernel build` 生成的元数据中找到监听器，因此项目需要用 SKERNEL 构建。

## 提供的能力

- `Psr\EventDispatcher\EventDispatcherInterface`，分发任何事件对象并返回它。
- 用 `#[Listener]` 和 `ListenerInterface` 声明、按优先级排序的监听器。
- 按类匹配：某个类或接口的监听器也会收到其子类和实现的事件。
- 通过 PSR-14 的 `StoppableEventInterface` 停止传播。
- `Psr\EventDispatcher\ListenerProviderInterface`，列出某个事件的监听器。

它不会：

- 异步分发或延迟事件；
- 收集监听器的返回值；
- 在运行时注册或移除监听器；
- 适配 Symfony 的事件分发器，这由 `super-kernel/framework` 负责。

## 快速开始

声明一个事件。将它保存为 `src/Account/UserRegistered.php`：

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

声明该事件的监听器。将它保存为 `src/Account/SendWelcomeMail.php`：

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

从脚本中分发事件。将它保存为 `bin/register.php`：

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

构建项目并运行脚本：

```bash
skernel build
php bin/register.php
```

它会打印 `Welcome mail sent to ada@example.com`。

## 公开接口

| 名称 | 类别 | 用途 |
| --- | --- | --- |
| `SuperKernel\Attribute\Listener` | 注解 | 把被注解的类注册为监听器；`priority` 决定其顺序，越高越先。 |
| `SuperKernel\Contract\ListenerInterface` | 接口 | 由监听器实现：`listen()` 列出它的事件，`process()` 处理一个事件。 |
| `Psr\EventDispatcher\EventDispatcherInterface` | 接口 | 由容器提供；把事件分发给它的监听器。 |
| `Psr\EventDispatcher\ListenerProviderInterface` | 接口 | 由容器提供；按顺序返回某个事件的监听器。 |

## 需要了解的行为

- 监听器同步运行，优先级高的先运行，优先级相同的按构建发现它们的顺序运行；`dispatch()` 返回同一个事件对象。
- 监听器抛出的异常原样从 `dispatch()` 向外传播，之后的监听器不会运行。
- 所有监听器在第一次需要分发器时创建。没有实现 `ListenerInterface` 的监听器，或者其 `listen()` 返回的不是由已存在的类名或接口名组成的列表，都会在那时抛出 `InvalidArgumentException`，导致任何事件都无法分发，`skernel build` 也会失败。
- 监听器从构建元数据中发现：添加、删除或修改监听器之后，请重新运行 `skernel build`，或者用会先构建的 `skernel run` 启动。

## 文档

开发者文档介绍了如何声明监听器、匹配多个事件、停止传播、监听器提供者以及错误消息：[SuperKernel Event Dispatcher](https://github.com/super-kernel/.github/blob/main/docs/components/event-dispatcher.zh-CN.md)。

## 许可证

基于 MIT 许可证发布，详见 [LICENSE](LICENSE)。
