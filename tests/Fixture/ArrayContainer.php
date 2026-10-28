<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Fixture;

use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_key_exists;
use function sprintf;

final class ArrayContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $requested = [];

    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(private readonly array $entries)
    {
    }

    public function get(string $id): mixed
    {
        $this->requested[] = $id;

        if (!array_key_exists($id, $this->entries)) {
            throw new RuntimeException(sprintf('Container entry %s is not defined.', $id));
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
