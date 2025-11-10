<?php
declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\Di\Attribute\Factory;
use SuperKernel\Di\Attribute\Provider;
use SuperKernel\Di\Contract\AttributeCollectorInterface;
use SuperKernel\Di\Contract\ReflectionCollectorInterface;
use SuperKernel\EventDispatcher\Attribute\Listener;
use SuperKernel\EventDispatcher\Exception\InvalidListenerException;
use SuperKernel\EventDispatcher\Provider\ListenerProvider;
use function is_subclass_of;

#[
	Provider(ListenerProviderInterface::class),
	Factory,
]
final class ListenerProviderFactory
{
	/**
	 * @param ContainerInterface           $container
	 * @param ReflectionCollectorInterface $reflectionManager
	 * @param AttributeCollectorInterface  $attributeCollector
	 *
	 * @return ListenerProviderInterface
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __invoke(
		ContainerInterface           $container,
		ReflectionCollectorInterface $reflectionManager,
		AttributeCollectorInterface  $attributeCollector,
	): ListenerProviderInterface
	{
		$listenerProvider = new ListenerProvider();

		foreach ($attributeCollector->getAttributes(Listener::class) as $class => $attributes) {
			if (!is_subclass_of($class, ListenerInterface::class)) {
				throw new InvalidListenerException($class);
			}

			/* @var Listener $attribute */
			$attribute = $attributes[0];

			$events = is_array($attribute->event) ? $attribute->event : [$attribute->event];

			$listener = $container->get($class);

			foreach ($events as $event) {
				$listenerProvider->insert(
					$event,
					[
						$listener,
						'process',
					],
					$attribute->priority,
				);
			}
		}

		return $listenerProvider;
	}
}