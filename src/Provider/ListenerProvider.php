<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Provider;

use Psr\EventDispatcher\ListenerProviderInterface;
use SplPriorityQueue;

final class ListenerProvider implements ListenerProviderInterface
{
	/**
	 * @var array<ListenerData>
	 */
	private array $listeners = [];

	public function add(string $event, callable $listener, int $priority = 0): void
	{
		$this->listeners[] = new ListenerData($event, $listener, $priority);
	}

	/**
	 * To support inheritance call chains, the current version does not consider the implementation of optimal
	 * performance.
	 *
	 * @param object $event
	 *
	 * @return iterable
	 */
	public function getListenersForEvent(object $event): iterable
	{
		$queue = new SplPriorityQueue();

		foreach ($this->listeners as $listener) {
			if ($event instanceof $listener->event) {
				$queue->insert($listener->listener, $listener->priority);
			}
		}

		return $queue;
	}
}