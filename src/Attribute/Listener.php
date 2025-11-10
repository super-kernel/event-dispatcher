<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Listener
{
	public function __construct(public string|array $event, public int $priority = 0)
	{
	}
}