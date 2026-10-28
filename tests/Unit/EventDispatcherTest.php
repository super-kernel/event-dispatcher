<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionClass;
use RuntimeException;
use SuperKernel\Attribute\Provider;
use SuperKernel\EventDispatcher\EventDispatcher;
use SuperKernel\EventDispatcher\Tests\Fixture\RecordingEvent;

final class EventDispatcherTest extends TestCase
{
    public function testDeclaresTheSharedProviderContract(): void
    {
        $dispatcher = new ReflectionClass(EventDispatcher::class);
        $attributes = $dispatcher->getAttributes(Provider::class);
        $constructor = $dispatcher->getConstructor();

        self::assertTrue($dispatcher->isFinal());
        self::assertTrue($dispatcher->isReadOnly());
        self::assertTrue($dispatcher->implementsInterface(EventDispatcherInterface::class));
        self::assertCount(1, $attributes);
        self::assertSame(EventDispatcherInterface::class, $attributes[0]->newInstance()->className);
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        self::assertCount(1, $constructor->getParameters());
        self::assertSame(1, $constructor->getNumberOfRequiredParameters());
        self::assertSame(ListenerProviderInterface::class, (string) $constructor->getParameters()[0]->getType());
    }

    public function testDispatchesSynchronouslyInProviderOrderAndReturnsTheSameEvent(): void
    {
        $provider = new readonly class([
            static function (RecordingEvent $event): void {
                $event->calls[] = 'first';
            },
            static function (RecordingEvent $event): void {
                $event->calls[] = 'second';
            },
            ]) implements ListenerProviderInterface {
                /**
                 * @param list<callable> $listeners
                 */
                public function __construct(private array $listeners)
                {
                }

                public function getListenersForEvent(object $event): iterable
                {
                    return $this->listeners;
                }
            };
        $dispatcher = new EventDispatcher($provider);
        $event = new RecordingEvent();

        $returned = $dispatcher->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame([
            'first',
            'second',
        ], $event->calls);
        self::assertSame(3, $event->propagationChecks);
    }

    public function testDoesNotCallAListenerWhenPropagationIsAlreadyStopped(): void
    {
        $provider = new class() implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable
            {
                yield static function (RecordingEvent $event): void {
                    $event->calls[] = 'unexpected';
                };
            }
        };
        $event = new RecordingEvent(true);

        $returned = new EventDispatcher($provider)->dispatch($event);

        self::assertSame($event, $returned);
        self::assertSame([], $event->calls);
        self::assertSame(1, $event->propagationChecks);
    }

    public function testStopsBeforeTheNextListener(): void
    {
        $provider = new class() implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable
            {
                yield static function (RecordingEvent $event): void {
                    $event->calls[] = 'first';
                    $event->stop();
                };
                yield static function (RecordingEvent $event): void {
                    $event->calls[] = 'unexpected';
                };
            }
        };
        $event = new RecordingEvent();

        new EventDispatcher($provider)->dispatch($event);

        self::assertSame([
            'first',
        ], $event->calls);
        self::assertSame(2, $event->propagationChecks);
    }

    public function testPropagatesTheOriginalListenerThrowable(): void
    {
        $exception = new RuntimeException('listener failed');
        $provider = new readonly class($exception) implements ListenerProviderInterface {
            public function __construct(private RuntimeException $exception)
            {
            }

            /**
             * The callback retains the signature required by its registration or reflection contract.
             *
             * @noinspection PhpUnusedParameterInspection
             */
            public function getListenersForEvent(object $event): iterable
            {
                yield
                function (object $event): void {
                    throw $this->exception;
                };
            }
        };

        try {
            new EventDispatcher($provider)->dispatch(new RecordingEvent());
            self::fail('The listener throwable must propagate.');
        } catch (RuntimeException $caught) {
            self::assertSame($exception, $caught);
        }
    }
}
