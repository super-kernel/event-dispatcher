<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use SuperKernel\Attribute\Contract;
use SuperKernel\Attribute\Factory;
use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Contract\ReflectionManagerInterface;
use SuperKernel\EventDispatcher\Exception\InvalidListenerException;
use SuperKernel\EventDispatcher\Provider\ListenerProvider;
use function is_string;
use function is_subclass_of;

#[
	Contract(ListenerProviderInterface::class),
	Factory
]
final class ListenerProviderFactory
{
	/**
	 * @param ContainerInterface         $container
	 * @param ReflectionManagerInterface $reflectionManager
	 *
	 * @return ListenerProviderInterface
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __invoke(
		ContainerInterface         $container,
		ReflectionManagerInterface $reflectionManager,
	): ListenerProviderInterface
	{
		$listenerProvider = new ListenerProvider();

		/* @var array<string> $listeners */
		$listeners = $reflectionManager->getAttributes(Listener::class);

		foreach ($listeners as $listener) {

			if (!is_subclass_of($listener, ListenerInterface::class)) {
				throw new InvalidListenerException($listener);
			}

			$annotations = $reflectionManager->getClassAnnotations($listener, Listener::class);

			/* @var Listener $annotation */
			$annotation = $annotations[0]->newInstance();
			$events     = is_string($annotation->event) ? [$annotation->event] : $annotation->event;
			/* @var ListenerInterface $listenerInstance */
			$listenerInstance = $container->get($listener);

			foreach ($events as $event) {
				$listenerProvider->add(
					$event,
					[
						$listenerInstance,
						'process',
					],
					$annotation->priority,
				);
			}
		}

		return $listenerProvider;
	}
}