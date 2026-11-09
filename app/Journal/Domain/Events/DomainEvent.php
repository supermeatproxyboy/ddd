<?php

namespace App\Journal\Domain\Events;

use DateTimeImmutable;

interface DomainEvent
{
    public function eventId(): string;

    public function eventType(): string;

    public function eventVersion(): int;

    public function aggregateType(): string;

    public function aggregateId(): int;

    public function ownerId(): int;

    public function occurredAt(): DateTimeImmutable;

    /** @return array<string, int|string|null> */
    public function payload(): array;
}
