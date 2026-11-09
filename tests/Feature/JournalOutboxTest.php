<?php

namespace Tests\Feature;

use App\Journal\Application\Commands\RecordEntryCommand;
use App\Journal\Application\Outbox\DomainEventOutbox;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\InvalidEntry;
use App\Journal\Domain\Entries\JournalDate;
use App\Journal\Domain\Events\DomainEvent;
use App\Journal\Domain\Events\EventRecordedDomainEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class JournalOutboxTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('entryKinds')]
    public function test_recording_an_entry_stores_a_pending_versioned_domain_event(EntryKind $kind): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08T20:15:16.123456-03:00'));
        $user = User::factory()->create();
        $content = $this->content($kind);

        $entry = $this->app->make(RecordEntryCommand::class)->handle($user->id, $content);
        $outboxMessage = DB::table('outbox_messages')->sole();

        $this->assertDatabaseCount('entries', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertSame('EventRecordedDomainEvent', $outboxMessage->event_type);
        $this->assertSame(1, $outboxMessage->event_version);
        $this->assertSame('journal_entry', $outboxMessage->aggregate_type);
        $this->assertSame($entry->id(), $outboxMessage->aggregate_id);
        $this->assertSame($user->id, $outboxMessage->owner_id);
        $this->assertNull($outboxMessage->published_at);
        $this->assertSame('2026-11-08 23:15:16.123456', $outboxMessage->occurred_at);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $outboxMessage->event_id);
        $this->assertSame([
            'entry_id' => $entry->id(),
            'owner_id' => $user->id,
            'title' => $content->title,
            'kind' => $kind->value,
            'occurred_on' => $content->occurredOn->value,
            'body' => $content->body,
            'reflection' => $content->reflection,
        ], json_decode($outboxMessage->payload, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_an_outbox_failure_rolls_back_both_the_entry_and_the_inserted_message(): void
    {
        $user = User::factory()->create();
        $databaseOutbox = $this->app->make(DomainEventOutbox::class);
        $this->app->instance(DomainEventOutbox::class, new class($databaseOutbox) implements DomainEventOutbox
        {
            public function __construct(private DomainEventOutbox $databaseOutbox) {}

            public function append(DomainEvent $domainEvent): void
            {
                $this->databaseOutbox->append($domainEvent);

                throw new RuntimeException('Simulated outbox failure after insertion.');
            }
        });

        try {
            $this->app->make(RecordEntryCommand::class)->handle($user->id, $this->content());
            $this->fail('A failed transaction must not return a recorded entry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated outbox failure after insertion.', $exception->getMessage());
        }

        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_a_failed_entry_insert_does_not_leave_an_outbox_message(): void
    {
        $user = User::factory()->create();
        $ownerId = $user->id;
        $user->delete();

        try {
            $this->app->make(RecordEntryCommand::class)->handle($ownerId, $this->content());
            $this->fail('An entry cannot be stored for a nonexistent owner.');
        } catch (QueryException) {
            $this->assertDatabaseCount('entries', 0);
            $this->assertDatabaseCount('outbox_messages', 0);
        }
    }

    public function test_a_domain_validation_failure_does_not_leave_an_entry_or_event(): void
    {
        $user = User::factory()->create();
        $content = new EntryContent('A future moment', EntryKind::Event,
            JournalDate::fromString(today()->addDay()->toDateString()), 'This has not happened.');

        try {
            $this->app->make(RecordEntryCommand::class)->handle($user->id, $content);
            $this->fail('Future-dated moments must not be recorded.');
        } catch (InvalidEntry) {
            $this->assertDatabaseCount('entries', 0);
            $this->assertDatabaseCount('outbox_messages', 0);
        }
    }

    public function test_a_serialization_failure_rolls_back_the_entry(): void
    {
        $user = User::factory()->create();
        $content = new EntryContent('A moment', EntryKind::Event,
            JournalDate::fromString(today()->toDateString()), "Invalid UTF-8: \xB1");

        try {
            $this->app->make(RecordEntryCommand::class)->handle($user->id, $content);
            $this->fail('Unserializable event payloads must not be stored.');
        } catch (JsonException) {
            $this->assertDatabaseCount('entries', 0);
            $this->assertDatabaseCount('outbox_messages', 0);
        }
    }

    public function test_reads_revisions_and_entry_deletion_do_not_reemit_or_change_the_recorded_event(): void
    {
        $user = User::factory()->create();
        $entry = $this->app->make(RecordEntryCommand::class)->handle($user->id, $this->content());
        $originalMessage = DB::table('outbox_messages')->sole();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->get(route('entries.edit', $entry->id()))->assertOk();
        $this->put(route('entries.update', $entry->id()), [
            'title' => 'A revised title',
            'kind' => 'achievement',
            'occurred_on' => today()->toDateString(),
            'body' => 'A revised story.',
            'reflection' => null,
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
        $this->delete(route('entries.destroy', $entry->id()))->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertEquals($originalMessage, DB::table('outbox_messages')->sole());
    }

    public function test_an_account_deletion_also_removes_its_outbox_payloads(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $recordEntryCommand = $this->app->make(RecordEntryCommand::class);
        $recordEntryCommand->handle($user->id, $this->content());
        $otherEntry = $recordEntryCommand->handle($otherUser->id, $this->content());

        $user->delete();

        $this->assertDatabaseMissing('outbox_messages', ['owner_id' => $user->id]);
        $this->assertDatabaseHas('outbox_messages', ['owner_id' => $otherUser->id, 'aggregate_id' => $otherEntry->id()]);
        $this->assertDatabaseCount('outbox_messages', 1);
    }

    public function test_a_domain_event_id_cannot_be_appended_twice(): void
    {
        $user = User::factory()->create();
        $entry = $this->app->make(RecordEntryCommand::class)->handle($user->id, $this->content());
        $domainEvent = EventRecordedDomainEvent::fromEntry($entry, now()->toDateTimeImmutable());
        $domainEventOutbox = $this->app->make(DomainEventOutbox::class);
        $domainEventOutbox->append($domainEvent);

        try {
            DB::transaction(fn () => $domainEventOutbox->append($domainEvent));
            $this->fail('The same domain event cannot be inserted twice.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('outbox_messages')->where('event_id', $domainEvent->eventId())->count());
            $this->assertDatabaseCount('outbox_messages', 2);
        }
    }

    /** @return iterable<string, array{EntryKind}> */
    public static function entryKinds(): iterable
    {
        foreach (EntryKind::cases() as $kind) {
            yield $kind->value => [$kind];
        }
    }

    private function content(EntryKind $kind = EntryKind::Event): EntryContent
    {
        return new EntryContent(
            title: 'A moment worth keeping',
            kind: $kind,
            occurredOn: JournalDate::fromString(today()->subDay()->toDateString()),
            body: 'Something meaningful happened.',
            reflection: 'I want to remember how it felt.',
        );
    }
}
