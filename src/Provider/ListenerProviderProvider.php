<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Provider;

use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\AttributeMetadataInterface;
use SuperKernel\EventDispatcher\ListenerProvider;

use function usort;

#[FactoryProvider(ListenerProviderInterface::class)]
final readonly class ListenerProviderProvider
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @throws NotFoundExceptionInterface  when a discovered listener identifier is missing from the container
     * @throws ContainerExceptionInterface when a discovered listener cannot be resolved
     * @throws InvalidArgumentException    when a discovered listener or its declared event list is invalid
     */
    public function __invoke(AnnotationCollectorInterface $collector): ListenerProviderInterface
    {
        $listeners = [];
        $order = 0;

        foreach ($collector->getClassesWithAttribute(Listener::class) as $className => [$attribute]) {
            $listeners[] = [
                'className' => $className,
                'priority'  => self::priority($attribute),
                'order'     => $order++,
            ];
        }

        usort(
            $listeners,
            static fn(array $left, array $right): int => $right['priority'] <=> $left['priority']
                ?: $left['order'] <=> $right['order'],
        );

        $listenerClasses = [];

        foreach ($listeners as $listener) {
            $listenerClasses[] = $listener['className'];
        }

        return new ListenerProvider(
            $this->container,
            ...$listenerClasses,
        );
    }

    /**
     * @param AttributeMetadataInterface<Listener> $attribute
     */
    private static function priority(AttributeMetadataInterface $attribute): int
    {
        return $attribute->attribute()->priority;
    }
}
