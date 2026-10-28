<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Fixture;

use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;

#[Listener(priority: 25)]
final class ParentEventListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            ParentEvent::class,
        ];
    }

    public function process(object $event): void
    {
    }
}
