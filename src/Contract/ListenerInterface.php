<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Contract;

interface ListenerInterface
{
	public function process(object $event): void;
}