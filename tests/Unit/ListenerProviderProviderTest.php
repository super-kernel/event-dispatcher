<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionClass;
use SuperKernel\Annotator\AnnotationCollector;
use SuperKernel\Annotator\Model\AttributeMetadata;
use SuperKernel\Annotator\Model\PackageAnnotations;
use SuperKernel\Attribute\FactoryProvider;
use SuperKernel\Attribute\Listener;
use SuperKernel\Contract\AnnotationCollectorInterface;
use SuperKernel\Contract\AttributeMetadataInterface;
use SuperKernel\Contract\ListenerInterface;
use SuperKernel\EventDispatcher\EventDispatcher;
use SuperKernel\EventDispatcher\Provider\ListenerProviderProvider;
use SuperKernel\EventDispatcher\Tests\Fixture\ArrayContainer;
use SuperKernel\EventDispatcher\Tests\Fixture\RecordingEvent;
use Throwable;

use function get_class;

final class ListenerProviderProviderTest extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function metadataImplementations(): iterable
    {
        yield 'Annotator models' => [
            false,
        ];
        yield 'independent metadata' => [
            true,
        ];
    }

    public function testDeclaresTheSharedFactoryProviderContract(): void
    {
        $provider = new ReflectionClass(ListenerProviderProvider::class);
        $attributes = $provider->getAttributes(FactoryProvider::class);
        $constructor = $provider->getConstructor();
        $invoke = $provider->getMethod('__invoke');

        self::assertTrue($provider->isFinal());
        self::assertTrue($provider->isReadOnly());
        self::assertCount(1, $attributes);
        self::assertSame(ListenerProviderInterface::class, $attributes[0]->newInstance()->className);
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPublic());
        self::assertSame(ListenerProviderProvider::class, $constructor->getDeclaringClass()->getName());
        self::assertCount(1, $constructor->getParameters());
        self::assertSame(
            ContainerInterface::class,
            (string) $constructor->getParameters()[0]->getType(),
        );
        self::assertTrue($invoke->isPublic());
        self::assertFalse($invoke->isStatic());
        self::assertCount(1, $invoke->getParameters());
        self::assertSame(1, $invoke->getNumberOfRequiredParameters());
        self::assertSame(AnnotationCollectorInterface::class, (string) $invoke->getParameters()[0]->getType());
        self::assertSame(ListenerProviderInterface::class, (string) $invoke->getReturnType());
    }

    /** @throws Throwable When fixture setup or the operation under test throws */
    #[DataProvider('metadataImplementations')]
    public function testDiscoversOrdersAndResolvesAnnotatedListeners(bool $independent): void
    {
        $lowFirst = new readonly class('low-first') implements ListenerInterface {
            public function __construct(private string $label)
            {
            }

            public function listen(): array
            {
                return [
                    RecordingEvent::class,
                ];
            }

            public function process(object $event): void
            {
                if ($event instanceof RecordingEvent) {
                    $event->calls[] = $this->label;
                }
            }
        };
        $high = new readonly class('high') implements ListenerInterface {
            public function __construct(private string $label)
            {
            }

            public function listen(): array
            {
                return [
                    RecordingEvent::class,
                ];
            }

            public function process(object $event): void
            {
                if ($event instanceof RecordingEvent) {
                    $event->calls[] = $this->label;
                }
            }
        };
        $lowSecond = new readonly class('low-second') implements ListenerInterface {
            public function __construct(private string $label)
            {
            }

            public function listen(): array
            {
                return [
                    RecordingEvent::class,
                ];
            }

            public function process(object $event): void
            {
                if ($event instanceof RecordingEvent) {
                    $event->calls[] = $this->label;
                }
            }
        };
        $lowFirstClass = get_class($lowFirst);
        $highClass = get_class($high);
        $lowSecondClass = get_class($lowSecond);
        $collector = $this->collector([
            $lowFirstClass => [
                new AttributeMetadata(new Listener()),
            ],
            $highClass => [
                new AttributeMetadata(new Listener(priority: 100)),
            ],
            $lowSecondClass => [
                new AttributeMetadata(new Listener(0)),
            ],
        ]);
        if ($independent) {
            $classes = [];

            foreach ($collector->getClassesWithAttribute(Listener::class) as $class => [$original]) {
                $metadata = $this->createStub(AttributeMetadataInterface::class);
                $metadata->method('attribute')->willReturn($original->attribute());
                $classes[$class] = [
                    $metadata,
                ];
            }

            $collector = $this->createMock(AnnotationCollectorInterface::class);
            $collector->expects($this->once())->method('getClassesWithAttribute')
                ->with(Listener::class)->willReturn($classes);
        }
        $container = new ArrayContainer([
            $lowFirstClass  => $lowFirst,
            $highClass      => $high,
            $lowSecondClass => $lowSecond,
        ]);
        $provider = new ListenerProviderProvider($container)($collector);
        $event = new RecordingEvent();

        new EventDispatcher($provider)->dispatch($event);

        self::assertSame([
            'high',
            'low-first',
            'low-second',
        ], $event->calls);
        self::assertSame([
            $highClass,
            $lowFirstClass,
            $lowSecondClass,
        ], $container->requested);
    }

    /**
     * @param array<class-string, list<AttributeMetadata<object>>> $classAttributes
     */
    private function collector(array $classAttributes): AnnotationCollectorInterface
    {
        return new AnnotationCollector([
            new PackageAnnotations(
                'super-kernel/event-dispatcher-tests',
                null,
                $classAttributes,
                [],
                [],
            ),
        ]);
    }
}
