<?php

namespace App\Journal\Application\Outbox;

use App\Journal\Domain\Events\DomainEvent;

interface DomainEventOutbox
{
    public function append(DomainEvent $domainEvent): void;
}
