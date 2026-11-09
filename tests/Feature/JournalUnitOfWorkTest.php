<?php

namespace Tests\Feature;

use App\Journal\Application\Outbox\DomainEventOutbox;
use App\Journal\Application\UnitOfWork\UnitOfWork;
use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\JournalDate;
use App\Journal\Domain\Events\DomainEvent;
use App\Journal\Domain\Events\EventRecordedDomainEvent;
use App\Journal\Infrastructure\Persistence\Eloquent\EntryRecord;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class JournalUnitOfWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_entries_and_their_outbox_events_commit_as_one_unit(): void
    {
        $user = User::factory()->create();
        $unitOfWork = $this->app->make(UnitOfWork::class);
        $firstEntry = $this->newEntry($user->id, 'First moment');
        $secondEntry = $this->newEntry($user->id, 'Second moment');

        foreach ([$firstEntry, $secondEntry] as $entry) {
            $unitOfWork->persist($entry);
            $unitOfWork->recordDomainEventAfterPersisting($entry, fn (Entry $persistedEntry) => EventRecordedDomainEvent::fromEntry($persistedEntry, now()->toDateTimeImmutable()));
        }

        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
        $result = $unitOfWork->commit();

        foreach ([$firstEntry, $secondEntry] as $entry) {
            $persistedEntry = $result->persistedEntry($entry);
            $this->assertDatabaseHas('entries', ['id' => $persistedEntry->id(), 'title' => $entry->content()->title]);
            $message = DB::table('outbox_messages')->where('aggregate_id', $persistedEntry->id())->sole();
            $this->assertSame($entry->content()->title, json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR)['title']);
        }

        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseCount('outbox_messages', 2);
        $this->assertFalse($unitOfWork->hasPendingChanges());
        $unitOfWork->commit();
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseCount('outbox_messages', 2);
    }

    public function test_a_later_entry_failure_rolls_back_earlier_writes_and_clears_the_unit(): void
    {
        $user = User::factory()->create();
        $removedUser = User::factory()->create();
        $removedUserId = $removedUser->id;
        $removedUser->delete();
        $unitOfWork = $this->app->make(UnitOfWork::class);
        $unitOfWork->persist($this->newEntry($user->id, 'Must roll back'));
        $unitOfWork->persist($this->newEntry($removedUserId, 'Invalid owner'));

        try {
            $unitOfWork->commit();
            $this->fail('A later persistence failure must abort the whole unit.');
        } catch (QueryException) {
            $this->assertDatabaseCount('entries', 0);
            $this->assertDatabaseCount('outbox_messages', 0);
            $this->assertFalse($unitOfWork->hasPendingChanges());
        }

        $validEntry = $this->newEntry($user->id, 'Fresh work');
        $unitOfWork->persist($validEntry);
        $unitOfWork->commit();
        $this->assertDatabaseCount('entries', 1);
        $this->assertDatabaseHas('entries', ['title' => 'Fresh work']);
    }

    public function test_an_outbox_failure_rolls_back_updates_deletions_and_inserted_events(): void
    {
        $user = User::factory()->create();
        $updatedRecord = EntryRecord::factory()->for($user)->create(['title' => 'Original title']);
        $deletedRecord = EntryRecord::factory()->for($user)->create();
        $databaseOutbox = $this->app->make(DomainEventOutbox::class);
        $this->app->instance(DomainEventOutbox::class, new class($databaseOutbox) implements DomainEventOutbox
        {
            public function __construct(private DomainEventOutbox $databaseOutbox) {}

            public function append(DomainEvent $domainEvent): void
            {
                $this->databaseOutbox->append($domainEvent);

                throw new RuntimeException('Outbox failed after insertion.');
            }
        });

        $unitOfWork = $this->app->make(UnitOfWork::class);
        $updatedEntry = Entry::reconstitute($updatedRecord->id, $user->id, $this->newEntry($user->id, 'Revised title')->content());
        $deletedEntry = Entry::reconstitute($deletedRecord->id, $user->id, $this->newEntry($user->id, 'Removed')->content());
        $unitOfWork->persist($updatedEntry);
        $unitOfWork->remove($deletedEntry);
        $unitOfWork->recordDomainEventAfterPersisting($updatedEntry, fn (Entry $entry) => EventRecordedDomainEvent::fromEntry($entry, now()->toDateTimeImmutable()));

        try {
            $unitOfWork->commit();
            $this->fail('The outbox failure must roll back the whole unit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Outbox failed after insertion.', $exception->getMessage());
        }

        $this->assertDatabaseHas('entries', ['id' => $updatedRecord->id, 'title' => 'Original title']);
        $this->assertModelExists($deletedRecord);
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->assertFalse($unitOfWork->hasPendingChanges());
    }

    public function test_event_factory_failures_also_roll_back_writes_and_clear_pending_state(): void
    {
        $user = User::factory()->create();
        $entry = $this->newEntry($user->id, 'Must roll back');
        $unitOfWork = $this->app->make(UnitOfWork::class);
        $unitOfWork->persist($entry);
        $unitOfWork->recordDomainEventAfterPersisting($entry, function (): never {
            throw new RuntimeException('Domain event factory failed.');
        });

        try {
            $unitOfWork->commit();
            $this->fail('A failed event factory must abort the commit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Domain event factory failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->assertFalse($unitOfWork->hasPendingChanges());
    }

    public function test_container_resolutions_do_not_share_pending_work(): void
    {
        $user = User::factory()->create();
        $firstUnit = $this->app->make(UnitOfWork::class);
        $secondUnit = $this->app->make(UnitOfWork::class);
        $firstUnit->persist($this->newEntry($user->id, 'Private pending work'));

        $this->assertNotSame($firstUnit, $secondUnit);
        $this->assertFalse($secondUnit->hasPendingChanges());
        $secondUnit->commit();
        $this->assertDatabaseCount('entries', 0);
        $firstUnit->discard();
    }

    private function newEntry(int $ownerId, string $title): Entry
    {
        return Entry::record($ownerId, new EntryContent($title, EntryKind::Event,
            JournalDate::fromString(today()->toDateString()), 'A story worth remembering.'), JournalDate::fromString(today()->toDateString()));
    }
}
