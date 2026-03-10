<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Provider;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use SuperKernel\Annotation\Factory;
use SuperKernel\Annotation\Provider;
use SuperKernel\Attribute\Contract\AttributeCollectorInterface;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Contract\ReflectorInterface;
use SuperKernel\EventDispatcher\Attribute\Listener;
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
	 * @param ContainerInterface          $container
	 * @param ReflectorInterface          $reflector
	 * @param AttributeCollectorInterface $attributeCollector
	 *
	 * @return ListenerProviderInterface
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __invoke(
		ContainerInterface          $container,
		ReflectorInterface          $reflector,
		AttributeCollectorInterface $attributeCollector,
	): ListenerProviderInterface
	{
		$listenerProvider = new ListenerProvider();

		foreach ($attributeCollector->getClassesByAttribute(Listener::class) as $attribute) {
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