<?php

namespace App\Journal\Domain\Events;

use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;

final readonly class EventRecordedDomainEvent implements DomainEvent
{
    public const EVENT_TYPE = 'EventRecordedDomainEvent';

    public const EVENT_VERSION = 1;

    private function __construct(
        private DomainEventId     $id,
        private int               $entryId,
        private int               $entryOwnerId,
        private EntryContent      $content,
        private DateTimeImmutable $recordedAt,
    ) {}

    public static function fromEntry(Entry $entry, DateTimeImmutable $recordedAt): self
    {
        return new self(
            id:           DomainEventId::generate(),
            entryId:      $entry->id() ?? throw new LogicException('An entry must be saved before its recorded event is created.'),
            entryOwnerId: $entry->ownerId(),
            content:      $entry->content(),
            recordedAt:   $recordedAt->setTimezone(new DateTimeZone('UTC')),
        );
    }

    public function eventId(): string
    {
        return $this->id->value;
    }

    public function eventType(): string
    {
        return self::EVENT_TYPE;
    }

    public function eventVersion(): int
    {
        return self::EVENT_VERSION;
    }

    public function aggregateType(): string
    {
        return Entry::AGGREGATE_TYPE;
    }

    public function aggregateId(): int
    {
        return $this->entryId;
    }

    public function ownerId(): int
    {
        return $this->entryOwnerId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /** @return array<string, int|string|null> */
    public function payload(): array
    {
        return [
            'entry_id'    => $this->entryId,
            'owner_id'    => $this->entryOwnerId,
            'title'       => $this->content->title,
            'kind'        => $this->content->kind->value,
            'occurred_on' => $this->content->occurredOn->value,
            'body'        => $this->content->body,
            'reflection'  => $this->content->reflection,
        ];
    }
}
