<?php

namespace Tests\Unit\Journal;

use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\JournalDate;
use App\Journal\Domain\Events\EventRecordedDomainEvent;
use App\Journal\Domain\Events\DomainEventId;
use App\Shared\Domain\ValueObject;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

class EventRecordedDomainEventTest extends TestCase
{
    public function test_the_event_has_a_stable_identity_and_captures_a_snapshot_in_utc(): void
    {
        $content = new EntryContent('A meaningful day', EntryKind::Achievement,
            JournalDate::fromString('2026-11-07'), 'I finished the project.', 'It took courage.');
        $entry = Entry::reconstitute(id: 12, ownerId: 7, content: $content);
        $domainEvent = EventRecordedDomainEvent::fromEntry($entry, new DateTimeImmutable('2026-11-08T20:15:16.123456-03:00'));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $domainEvent->eventId());
        $this->assertSame('EventRecordedDomainEvent', $domainEvent->eventType());
        $this->assertSame(1, $domainEvent->eventVersion());
        $this->assertSame('journal_entry', $domainEvent->aggregateType());
        $this->assertSame(12, $domainEvent->aggregateId());
        $this->assertSame(7, $domainEvent->ownerId());
        $this->assertSame('2026-11-08T23:15:16.123456+00:00', $domainEvent->occurredAt()->format('Y-m-d\TH:i:s.uP'));

        $entry->revise(new EntryContent('Revised title', EntryKind::Event,
            JournalDate::fromString('2026-11-08'), 'A revised story.'), JournalDate::fromString('2026-11-08'));

        $this->assertSame([
            'entry_id' => 12,
            'owner_id' => 7,
            'title' => 'A meaningful day',
            'kind' => 'achievement',
            'occurred_on' => '2026-11-07',
            'body' => 'I finished the project.',
            'reflection' => 'It took courage.',
        ], $domainEvent->payload());
    }

    public function test_distinct_facts_have_distinct_event_ids(): void
    {
        $entry = Entry::reconstitute(12, 7, new EntryContent('A meaningful day', EntryKind::Event,
            JournalDate::fromString('2026-11-08'), 'A story.'));
        $recordedAt = new DateTimeImmutable('2026-11-08T18:00:00+00:00');

        $this->assertNotSame(
            EventRecordedDomainEvent::fromEntry($entry, $recordedAt)->eventId(),
            EventRecordedDomainEvent::fromEntry($entry, $recordedAt)->eventId(),
        );
    }

    public function test_an_event_cannot_reference_an_unsaved_entry(): void
    {
        $entry = Entry::record(7, new EntryContent('A meaningful day', EntryKind::Event,
            JournalDate::fromString('2026-11-08'), 'A story.'), JournalDate::fromString('2026-11-08'));
        $this->expectException(LogicException::class);

        EventRecordedDomainEvent::fromEntry($entry, new DateTimeImmutable('2026-11-08T18:00:00+00:00'));
    }

    public function test_domain_event_identifiers_are_value_objects(): void
    {
        $id = DomainEventId::generate();

        $this->assertInstanceOf(ValueObject::class, $id);
        $this->assertTrue($id->equals(clone $id));
        $this->assertFalse($id->equals(DomainEventId::generate()));
    }
}
