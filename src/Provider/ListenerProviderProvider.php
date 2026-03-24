<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Provider;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use SuperKernel\Attribute\Factory;
use SuperKernel\Attribute\Listener;
use SuperKernel\Attribute\Provider;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Contract\ReflectionCollectorInterface;
use SuperKernel\EventDispatcher\Exception\InvalidListenerException;
use SuperKernel\EventDispatcher\ListenerProvider;
use function is_subclass_of;

#[
	Provider(ListenerProviderInterface::class),
	Factory,
]
final class ListenerProviderProvider
{
	/**
	 * @param ContainerInterface           $container
	 * @param ReflectionCollectorInterface $reflectionCollector
	 * @param AnnotationCollectorInterface $annotationCollector
	 *
	 * @return ListenerProviderInterface
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __invoke(
		ContainerInterface           $container,
		ReflectionCollectorInterface $reflectionCollector,
		AnnotationCollectorInterface $annotationCollector,
	): ListenerProviderInterface
	{
		$listenerProvider = new ListenerProvider();

		foreach ($annotationCollector->getClassesByAttribute(Listener::class) as $attribute) {
			$class = $attribute->getClass();

			if (!is_subclass_of($class, ListenerInterface::class)) {
				throw new InvalidListenerException($class);
			}

			/* @var ListenerInterface $listener */
			$listener = $container->get($class);

			foreach ($listener->listen() as $event) {
				$listenerProvider->insert(
					$event,
					[
						$listener,
						'process',
					],
				);
			}
		}

		return $listenerProvider;
	}
}