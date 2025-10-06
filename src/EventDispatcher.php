<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use SuperKernel\Attribute\Contract;

#[
	Contract(EventDispatcherInterface::class),
]
final readonly class EventDispatcher implements EventDispatcherInterface
{
	public function __construct(private ListenerProviderInterface $provider)
	{
	}

	/**
	 * @inheritdoc
	 */
	public function dispatch(object $event): object
	{
		foreach ($this->provider->getListenersForEvent($event) as $listener) {

			$listener($event);

			if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
				break;
			}
		}

		return $event;
	}
}