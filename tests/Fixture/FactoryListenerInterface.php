<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Fixture;

use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;

#[Listener]
interface FactoryListenerInterface extends ListenerInterface
{
}
