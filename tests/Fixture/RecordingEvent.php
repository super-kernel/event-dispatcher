<?php

declare(strict_types=1);

namespace SuperKernel\EventDispatcher\Tests\Fixture;

use Psr\EventDispatcher\StoppableEventInterface;

final class RecordingEvent implements StoppableEventInterface
{
    /** @var list<string> */
    public array $calls = [];

    public int $propagationChecks = 0;

    public function __construct(private bool $stopped = false)
    {
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        ++$this->propagationChecks;

        return $this->stopped;
    }
}
