<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Fixture;

use stdClass;
use SuperKernel\Contract\ListenerInterface;

final class AllEventListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            ParentEvent::class,
            stdClass::class,
        ];
    }

    public function process(object $event): void
    {
    }
}
