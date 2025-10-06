<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Exception;

use InvalidArgumentException;
use SuperKernel\Contract\ListenerInterface;
use Throwable;

/**
 * Thrown when a developer registers a listener that does not implement the ListenerInterface required by the framework.
 */
final class InvalidListenerException extends InvalidArgumentException
{
	public function __construct(
		string     $listener,
		int        $code = 0,
		?Throwable $previous = null,
	)
	{
		parent::__construct(
			sprintf(
				'Listener "%s" must implement interface "%s".',
				$listener,
				ListenerInterface::class,
			),
			$code,
			$previous,
		);
	}
}