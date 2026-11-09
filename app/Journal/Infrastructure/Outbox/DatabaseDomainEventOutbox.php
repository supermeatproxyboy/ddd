<?php

namespace App\Journal\Infrastructure\Outbox;

use App\Journal\Application\Outbox\DomainEventOutbox;
use App\Journal\Domain\Events\DomainEvent;
use DateTimeZone;
use Illuminate\Database\DatabaseManager;

final readonly class DatabaseDomainEventOutbox implements DomainEventOutbox
{
    public function __construct(
        private DatabaseManager $database,
    ) {}

    public function append(DomainEvent $domainEvent): void
    {
        $this->database->connection()->table('outbox_messages')->insert([
            'event_id' => $domainEvent->eventId(),
            'event_type' => $domainEvent->eventType(),
            'event_version' => $domainEvent->eventVersion(),
            'aggregate_type' => $domainEvent->aggregateType(),
            'aggregate_id' => $domainEvent->aggregateId(),
            'owner_id' => $domainEvent->ownerId(),
            'payload' => json_encode($domainEvent->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'occurred_at' => $domainEvent->occurredAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'published_at' => null,
        ]);
    }
}
