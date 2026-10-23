<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use SuperKernel;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\EventDispatcher\EventDispatcher;
use SuperKernel\EventDispatcher\ListenerProvider;
use SuperKernel\EventDispatcher\Tests\Fixture\AllEventListener;
use SuperKernel\EventDispatcher\Tests\Fixture\ArrayContainer;
use SuperKernel\EventDispatcher\Tests\Fixture\ChildEvent;
use SuperKernel\EventDispatcher\Tests\Fixture\ChildEventListener;
use SuperKernel\EventDispatcher\Tests\Fixture\FactoryListenerInterface;
use SuperKernel\EventDispatcher\Tests\Fixture\ParentEvent;
use SuperKernel\EventDispatcher\Tests\Fixture\ParentEventListener;
use Throwable;
use WeakReference;

use function get_class;
use function iterator_to_array;

final class ListenerProviderTest extends TestCase
{
    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testReturnsDeclaredListenersInConfiguredOrderIncludingParentTypes(): void
    {
        $childListener = new ChildEventListener();
        $parentListener = new ParentEventListener();
        $allListener = new AllEventListener();
        $container = new ArrayContainer([
            ChildEventListener::class  => $childListener,
            ParentEventListener::class => $parentListener,
            AllEventListener::class    => $allListener,
        ]);
        $provider = new ListenerProvider(
            $container,
            ChildEventListener::class,
            ParentEventListener::class,
            AllEventListener::class,
        );

        $listeners = iterator_to_array($provider->getListenersForEvent(new ChildEvent()), false);

        self::assertSame([
            [
                $childListener,
                'process',
            ],
            [
                $parentListener,
                'process',
            ],
            [
                $allListener,
                'process',
            ],
        ], $listeners);
        self::assertSame([
            ChildEventListener::class,
            ParentEventListener::class,
            AllEventListener::class,
        ], $container->requested);
    }

    /** @throws Throwable When listener resolution or dispatch fails */
    public function testRepeatedDispatchPreservesOrderAndMatchesOverlappingEventTypesOnce(): void
    {
        $listener = new class() implements ListenerInterface {
            public int $calls = 0;

            public function listen(): array
            {
                return [
                    SuperKernel\EventDispatcher\Tests\Fixture\EventTypeInterface::class,
                    SuperKernel\EventDispatcher\Tests\Fixture\ParentEvent::class,
                    SuperKernel\EventDispatcher\Tests\Fixture\ChildEvent::class,
                ];
            }

            public function process(object $event): void
            {
                ++$this->calls;
            }
        };
        $parentListener = new ParentEventListener();
        $childListener = new ChildEventListener();
        $listenerClass = $listener::class;
        $container = new ArrayContainer([
            ParentEventListener::class => $parentListener,
            $listenerClass             => $listener,
            ChildEventListener::class  => $childListener,
        ]);
        $provider = new ListenerProvider(
            $container,
            ParentEventListener::class,
            $listenerClass,
            ChildEventListener::class,
        );
        $dispatcher = new EventDispatcher($provider);
        $expected = [
            [
                $parentListener,
                'process',
            ],
            [
                $listener,
                'process',
            ],
            [
                $childListener,
                'process',
            ],
        ];

        foreach ([
            new ChildEvent(),
            new ChildEvent(),
        ] as $event) {
            self::assertSame($expected, iterator_to_array($provider->getListenersForEvent($event), false));
            self::assertSame($event, $dispatcher->dispatch($event));
        }

        self::assertSame(2, $listener->calls);
        self::assertSame([
            ParentEventListener::class,
            $listenerClass,
            ChildEventListener::class,
        ], $container->requested);
    }

    /** @throws Throwable When the listener cannot be resolved */
    public function testRepeatedEmptyAndInterfaceMatchesRemainSeparateByEventClass(): void
    {
        $listener = $this->createMock(FactoryListenerInterface::class);
        $listener->expects(self::once())->method('listen')->willReturn([
            SuperKernel\EventDispatcher\Tests\Fixture\EventTypeInterface::class,
        ]);
        $provider = new ListenerProvider(new ArrayContainer([
            FactoryListenerInterface::class => $listener,
        ]), FactoryListenerInterface::class);

        self::assertSame([], iterator_to_array($provider->getListenersForEvent(new ParentEvent()), false));
        self::assertSame([], iterator_to_array($provider->getListenersForEvent(new ParentEvent()), false));
        $expected = [
            [
                $listener,
                'process',
            ],
        ];
        self::assertSame($expected, iterator_to_array($provider->getListenersForEvent(new ChildEvent()), false));
        self::assertSame($expected, iterator_to_array($provider->getListenersForEvent(new ChildEvent()), false));
        self::assertSame([], iterator_to_array($provider->getListenersForEvent(new ParentEvent()), false));
    }

    /** @throws Throwable When the listeners cannot be resolved */
    public function testMatchingResultsRemainSpecificToEachProvider(): void
    {
        $firstListener = new ChildEventListener();
        $secondListener = new ChildEventListener();
        $first = new ListenerProvider(new ArrayContainer([
            ChildEventListener::class => $firstListener,
        ]), ChildEventListener::class);
        $second = new ListenerProvider(new ArrayContainer([
            ChildEventListener::class => $secondListener,
        ]), ChildEventListener::class);
        $event = new ChildEvent();
        $firstCallbacks = iterator_to_array($first->getListenersForEvent($event), false);
        $secondCallbacks = iterator_to_array($second->getListenersForEvent($event), false);

        self::assertSame([
            [
                $firstListener,
                'process',
            ],
        ], $firstCallbacks);
        self::assertSame([
            [
                $secondListener,
                'process',
            ],
        ], $secondCallbacks);
        self::assertSame($firstCallbacks, iterator_to_array($first->getListenersForEvent($event), false));
    }

    /** @throws Throwable When the listeners cannot be resolved */
    public function testStoppingEnumerationEarlyDoesNotTruncateLaterMatchingResults(): void
    {
        $childListener = new ChildEventListener();
        $parentListener = new ParentEventListener();
        $provider = new ListenerProvider(new ArrayContainer([
            ChildEventListener::class  => $childListener,
            ParentEventListener::class => $parentListener,
        ]), ChildEventListener::class, ParentEventListener::class);

        foreach ($provider->getListenersForEvent(new ChildEvent()) as $listener) {
            self::assertSame([
                $childListener,
                'process',
            ], $listener);

            break;
        }

        self::assertSame([
            [
                $childListener,
                'process',
            ],
            [
                $parentListener,
                'process',
            ],
        ], iterator_to_array($provider->getListenersForEvent(new ChildEvent()), false));
    }

    /** @throws Throwable When the listener cannot be resolved */
    public function testMatchingDoesNotRetainTheEventObject(): void
    {
        $listener = new ChildEventListener();
        $provider = new ListenerProvider(new ArrayContainer([
            ChildEventListener::class => $listener,
        ]), ChildEventListener::class);
        $event = new ChildEvent();
        $reference = WeakReference::create($event);
        $callbacks = iterator_to_array($provider->getListenersForEvent($event), false);
        unset($event);

        self::assertNull($reference->get());
        self::assertSame($callbacks, iterator_to_array($provider->getListenersForEvent(new ChildEvent()), false));
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testSkipsListenersThatDoNotDeclareTheEvent(): void
    {
        $childListener = new ChildEventListener();
        $parentListener = new ParentEventListener();
        $allListener = new AllEventListener();
        $container = new ArrayContainer([
            ChildEventListener::class  => $childListener,
            ParentEventListener::class => $parentListener,
            AllEventListener::class    => $allListener,
        ]);
        $provider = new ListenerProvider(
            $container,
            ChildEventListener::class,
            ParentEventListener::class,
            AllEventListener::class,
        );

        $listeners = iterator_to_array($provider->getListenersForEvent(new ParentEvent()), false);

        self::assertSame([
            [
                $parentListener,
                'process',
            ],
            [
                $allListener,
                'process',
            ],
        ], $listeners);
        self::assertSame([
            ChildEventListener::class,
            ParentEventListener::class,
            AllEventListener::class,
        ], $container->requested);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testSupportsAListenerDeclaringMultipleEventTypes(): void
    {
        $listener = new class() implements ListenerInterface {
            /**
             * @var list<class-string>
             */
            public array $processedEvents = [];

            public function listen(): array
            {
                return [
                    ParentEvent::class,
                    stdClass::class,
                ];
            }

            public function process(object $event): void
            {
                $this->processedEvents[] = match (true) {
                    $event instanceof ParentEvent => ParentEvent::class,
                    $event instanceof stdClass    => stdClass::class,
                };
            }
        };
        $listenerClass = get_class($listener);
        $container = new ArrayContainer([
            $listenerClass => $listener,
        ]);
        $provider = new ListenerProvider($container, $listenerClass);
        $parentListeners = iterator_to_array(
            $provider->getListenersForEvent(new ParentEvent()),
            false,
        );
        $standardListeners = iterator_to_array(
            $provider->getListenersForEvent(new stdClass()),
            false,
        );

        self::assertSame(
            [
                [
                    $listener,
                    'process',
                ],
            ],
            $parentListeners,
        );
        self::assertSame(
            [
                [
                    $listener,
                    'process',
                ],
            ],
            $standardListeners,
        );

        $parentListeners[0](new ParentEvent());
        $standardListeners[0](new stdClass());

        self::assertSame([
            ParentEvent::class,
            stdClass::class,
        ], $listener->processedEvents);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsAResolvedListenerWithoutTheSharedContract(): void
    {
        $listener = new class() {
            public function listen(): array
            {
                return [
                    ParentEvent::class,
                ];
            }

            public function process(object $event): void
            {
            }
        };
        $listenerClass = get_class($listener);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(ListenerInterface::class);

        new ListenerProvider(new ArrayContainer([
            $listenerClass => $listener,
        ]), $listenerClass);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsAssociativeEventDeclarations(): void
    {
        $listener = new class() implements ListenerInterface {
            public function listen(): array
            {
                return [
                    'event' => ParentEvent::class,
                ];
            }

            public function process(object $event): void
            {
            }
        };
        $listenerClass = get_class($listener);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must return a list');

        new ListenerProvider(new ArrayContainer([
            $listenerClass => $listener,
        ]), $listenerClass);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testRejectsValuesThatAreNotEventClassNames(): void
    {
        $listener = new class() implements ListenerInterface {
            public function listen(): array
            {
                return [
                    'missing-event-class',
                ];
            }

            public function process(object $event): void
            {
            }
        };
        $listenerClass = get_class($listener);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('existing event class or interface names');

        new ListenerProvider(new ArrayContainer([
            $listenerClass => $listener,
        ]), $listenerClass);
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    public function testSupportsAListenerContractResolvedByTheContainer(): void
    {
        $listener = new class() implements FactoryListenerInterface {
            public function listen(): array
            {
                return [
                    ParentEvent::class,
                ];
            }

            public function process(object $event): void
            {
            }
        };
        $container = new ArrayContainer([
            FactoryListenerInterface::class => $listener,
        ]);
        $provider = new ListenerProvider($container, FactoryListenerInterface::class);

        self::assertSame(
            [
                [
                    $listener,
                    'process',
                ],
            ],
            iterator_to_array($provider->getListenersForEvent(new ParentEvent()), false),
        );
        self::assertSame([
            FactoryListenerInterface::class,
        ], $container->requested);
    }
}
