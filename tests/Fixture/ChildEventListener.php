<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Fixture;

use SuperKernel\Contract\ListenerInterface;

final class ChildEventListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            ChildEvent::class,
        ];
    }

    public function process(object $event): void
    {
    }
}
