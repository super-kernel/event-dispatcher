<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Listener
{
	public array $event;

	public function __construct(string|array $event, public int $priority = 0)
	{
		$this->event = is_string($event) ? [$event] : $event;
	}
}