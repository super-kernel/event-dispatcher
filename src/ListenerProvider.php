<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher;

use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use SuperKernel\Contract\ListenerInterface;

use function array_is_list;
use function class_exists;
use function get_debug_type;
use function interface_exists;
use function is_a;
use function is_string;
use function sprintf;

final class ListenerProvider implements ListenerProviderInterface
{
    private const string INVALID_EVENTS_MESSAGE
        = '%s::listen() must return a list containing only existing event class or interface names.';

    /**
     * @var list<array{
     *     listener: ListenerInterface,
     *     eventClasses: list<class-string>
     * }>
     */
    private readonly array $listeners;

    /** @var array<class-string, list<array{ListenerInterface, 'process'}>> */
    private array $resolvedListeners = [];

    /**
     * @param class-string ...$listenerClasses Priority-ordered listener identifiers
     *
     * @throws NotFoundExceptionInterface  when a listener identifier is not registered in the container
     * @throws ContainerExceptionInterface when the container cannot resolve a listener
     * @throws InvalidArgumentException    when a resolved listener or its declared event list is invalid
     */
    public function __construct(ContainerInterface $container, string ...$listenerClasses)
    {
        $listeners = [];

        foreach ($listenerClasses as $className) {
            $listener = $container->get($className);

            if (!$listener instanceof ListenerInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Resolved listener %s must implement %s, %s provided.',
                    $className,
                    ListenerInterface::class,
                    get_debug_type($listener),
                ));
            }

            $eventClasses = $listener->listen();

            if (!array_is_list($eventClasses)) {
                throw new InvalidArgumentException(sprintf(self::INVALID_EVENTS_MESSAGE, $className));
            }

            foreach ($eventClasses as $eventClass) {
                if (
                    !is_string($eventClass)
                    || (!class_exists($eventClass) && !interface_exists($eventClass))
                ) {
                    throw new InvalidArgumentException(sprintf(self::INVALID_EVENTS_MESSAGE, $className));
                }
            }

            $listeners[] = [
                'listener'     => $listener,
                'eventClasses' => $eventClasses,
            ];
        }

        $this->listeners = $listeners;
    }

    public function getListenersForEvent(object $event): iterable
    {
        $eventType = $event::class;

        if (!isset($this->resolvedListeners[$eventType])) {
            $listeners = [];

            foreach ($this->listeners as $registration) {
                foreach ($registration['eventClasses'] as $eventClass) {
                    if (!is_a($event, $eventClass)) {
                        continue;
                    }

                    $listeners[] = [
                        $registration['listener'],
                        'process',
                    ];

                    break;
                }
            }

            $this->resolvedListeners[$eventType] = $listeners;
        }

        yield from $this->resolvedListeners[$eventType];
    }
}
