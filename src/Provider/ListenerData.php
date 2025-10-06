<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Provider;

final class ListenerData
{
	/**
	 * @var callable
	 */
	public readonly mixed $listener;

	public function __construct(public string $event, callable $listener, public int $priority)
	{
		$this->listener = $listener;
	}
}