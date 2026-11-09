<?php

namespace Tests\Unit\Journal;

use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\InvalidEntry;
use App\Journal\Domain\Entries\JournalDate;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Entity;
use App\Shared\Domain\ValueObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntryTest extends TestCase
{
    public function test_a_moment_is_recorded_without_a_framework_or_database(): void
    {
        $content = new EntryContent('  Finished the project  ', EntryKind::Accomplishment,
            JournalDate::fromString('2026-11-08'), '  I followed through.  ', '  ');
        $entry = Entry::record(7, $content, JournalDate::fromString('2026-11-08'));

        $this->assertNull($entry->id());
        $this->assertSame(7, $entry->ownerId());
        $this->assertSame('Finished the project', $entry->content()->title);
        $this->assertSame('I followed through.', $entry->content()->body);
        $this->assertNull($entry->content()->reflection);
    }

    public function test_a_revision_keeps_identity_and_ownership(): void
    {
        $entry = Entry::reconstitute(12, 7, $this->content());
        $revised = $this->content(title: 'A milestone', kind: EntryKind::Achievement);

        $entry->revise($revised, JournalDate::fromString('2026-11-08'));

        $this->assertSame(12, $entry->id());
        $this->assertSame(7, $entry->ownerId());
        $this->assertSame($revised, $entry->content());
    }

    public function test_recording_a_future_moment_is_a_domain_violation(): void
    {
        $this->expectException(InvalidEntry::class);

        Entry::record(7, $this->content(date: '2026-11-09'), JournalDate::fromString('2026-11-08'));
    }

    public function test_a_failed_revision_does_not_mutate_the_aggregate(): void
    {
        $original = $this->content();
        $entry = Entry::reconstitute(12, 7, $original);

        try {
            $entry->revise($this->content(date: '2026-11-09'), JournalDate::fromString('2026-11-08'));
            $this->fail('A future-dated revision must be rejected.');
        } catch (InvalidEntry $exception) {
            $this->assertArrayHasKey('occurred_on', $exception->errors);
            $this->assertSame($original, $entry->content());
        }
    }

    public function test_character_limits_count_unicode_characters(): void
    {
        $content = new EntryContent(str_repeat('á', EntryContent::TITLE_LIMIT), EntryKind::Event,
            JournalDate::fromString('2026-11-08'), 'A meaningful day.');

        $this->assertSame(160, mb_strlen($content->title));
    }

    #[DataProvider('invalidContents')]
    public function test_invalid_content_is_rejected_outside_http(string $field, string $value): void
    {
        $values = ['title' => 'A meaningful day', 'body' => 'A moment I want to remember.', 'reflection' => null];
        $values[$field] = $value;

        try {
            new EntryContent($values['title'], EntryKind::Event, JournalDate::fromString('2026-11-08'),
                $values['body'], $values['reflection']);
            $this->fail('Invalid content must be rejected by the domain.');
        } catch (InvalidEntry $exception) {
            $this->assertArrayHasKey($field, $exception->errors);
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidContents(): iterable
    {
        yield 'blank title' => ['title', '   '];
        yield 'long title' => ['title', str_repeat('a', 161)];
        yield 'blank story' => ['body', " \n "];
        yield 'long story' => ['body', str_repeat('a', 20001)];
        yield 'long reflection' => ['reflection', str_repeat('a', 5001)];
    }

    #[DataProvider('invalidDates')]
    public function test_occurrence_dates_must_be_real_calendar_dates(string $value): void
    {
        $this->expectException(InvalidEntry::class);

        JournalDate::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDates(): iterable
    {
        yield 'non leap year' => ['2025-02-29'];
        yield 'invalid day' => ['2026-02-30'];
        yield 'invalid month' => ['2026-13-01'];
        yield 'unformatted' => ['2026-11-8'];
        yield 'year zero' => ['0000-01-01'];
        yield 'timestamp' => ['2026-11-08T00:00:00Z'];
    }

    public function test_a_leap_day_is_preserved_as_a_date_without_a_timezone_conversion(): void
    {
        $this->assertSame('2024-02-29', JournalDate::fromString('2024-02-29')->value);
    }

    public function test_an_entry_is_an_aggregate_root_with_stable_entity_identity(): void
    {
        $first = Entry::reconstitute(12, 7, $this->content());
        $second = Entry::reconstitute(12, 7, $this->content(title: 'A revised story'));

        $this->assertInstanceOf(AggregateRoot::class, $first);
        $this->assertInstanceOf(Entity::class, $first);
        $this->assertSame('journal_entry', $first->aggregateType());
        $this->assertTrue($first->sameIdentityAs($second));
        $this->assertFalse($first->content()->equals($second->content()));
        $this->assertFalse($first->sameIdentityAs(Entry::reconstitute(13, 7, $this->content())));
    }

    public function test_content_value_equality_uses_normalized_fields_and_nested_date_values(): void
    {
        $first = new EntryContent('  A day  ', EntryKind::Event, JournalDate::fromString('2026-11-08'), '  A story.  ', '  ');
        $second = new EntryContent('A day', EntryKind::Event, JournalDate::fromString('2026-11-08'), 'A story.', null);

        $this->assertInstanceOf(ValueObject::class, $first);
        $this->assertNotSame($first->occurredOn, $second->occurredOn);
        $this->assertTrue($first->equals($second));
        $this->assertFalse($first->equals(new EntryContent('A day', EntryKind::Achievement, JournalDate::fromString('2026-11-08'), 'A story.')));
        $this->assertFalse($first->equals(new EntryContent('A day', EntryKind::Event, JournalDate::fromString('2026-11-07'), 'A story.')));
        $this->assertFalse($first->equals(new EntryContent('A day', EntryKind::Event, JournalDate::fromString('2026-11-08'), 'A story.', 'A reflection.')));
    }

    public function test_journal_dates_are_equal_by_calendar_value(): void
    {
        $first = JournalDate::fromString('2026-11-08');

        $this->assertInstanceOf(ValueObject::class, $first);
        $this->assertTrue($first->equals(JournalDate::fromString('2026-11-08')));
        $this->assertFalse($first->equals(JournalDate::fromString('2026-11-09')));
    }

    private function content(string $title = 'A meaningful day', string $date = '2026-11-08', EntryKind $kind = EntryKind::Event): EntryContent
    {
        return new EntryContent($title, $kind, JournalDate::fromString($date), 'A moment I want to remember.');
    }
}
