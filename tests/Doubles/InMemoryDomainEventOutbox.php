<?php

namespace Tests\Doubles;

use App\Journal\Application\Outbox\DomainEventOutbox;
use App\Journal\Domain\Events\DomainEvent;
use LogicException;

final class InMemoryDomainEventOutbox implements DomainEventOutbox
{
    /** @var array<string, DomainEvent> */
    public array $events = [];

    public function append(DomainEvent $domainEvent): void
    {
        if (isset($this->events[$domainEvent->eventId()])) {
            throw new LogicException('The domain event has already been stored.');
        }

        $this->events[$domainEvent->eventId()] = $domainEvent;
    }
}
