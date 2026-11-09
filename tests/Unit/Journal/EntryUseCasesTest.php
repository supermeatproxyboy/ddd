<?php

namespace Tests\Unit\Journal;

use App\Journal\Application\Commands\ForgetEntryCommand;
use App\Journal\Application\Commands\RecordEntryCommand;
use App\Journal\Application\Commands\ReviseEntryCommand;
use App\Journal\Application\Queries\FindEntryQuery;
use App\Journal\Application\Transactions\TransactionManager;
use App\Journal\Application\UnitOfWork\UnitOfWork;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\EntryNotFound;
use App\Journal\Domain\Entries\InvalidEntry;
use App\Journal\Domain\Entries\JournalDate;
use App\Journal\Domain\JournalClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryDomainEventOutbox;
use Tests\Doubles\InMemoryEntryRepository;

class EntryUseCasesTest extends TestCase
{
    private InMemoryEntryRepository $entriesRepository;

    private JournalClock $clock;

    private TransactionManager $transactionManager;

    private InMemoryDomainEventOutbox $domainEventOutbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entriesRepository = new InMemoryEntryRepository;
        $this->clock = new class implements JournalClock
        {
            public function today(): JournalDate
            {
                return JournalDate::fromString('2026-11-08');
            }

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-08T18:00:00+00:00');
            }
        };
        $this->domainEventOutbox = new InMemoryDomainEventOutbox;
        $this->transactionManager = $this->createMock(TransactionManager::class);
        $this->transactionManager->method('transactional')->willReturnCallback(fn (callable $operation) => $operation());
    }

    public function test_the_journal_lifecycle_works_without_laravel_or_eloquent(): void
    {
        $entry = $this->recordEntryCommand()->handle(7, $this->content());
        $id = $entry->id();
        $this->assertNotNull($id);

        (new ReviseEntryCommand($this->entriesRepository, $this->clock, $this->unitOfWork()))->handle(7, $id, $this->content('A revised memory'));
        $view = (new FindEntryQuery($this->entriesRepository))->handle(7, $id);
        $this->assertSame('A revised memory', $view->title);
        $this->assertSame('2026-11-08', $view->occurredOn);
        $this->assertSame(7, $this->entriesRepository->getOwned(7, $id)->ownerId());
        $this->assertCount(1, $this->domainEventOutbox->events);

        (new ForgetEntryCommand($this->entriesRepository, $this->unitOfWork()))->handle(7, $id);
        $this->assertSame(0, $this->entriesRepository->count());
    }

    public function test_recording_uses_the_injected_clock_and_cannot_save_a_future_moment(): void
    {
        try {
            $this->recordEntryCommand()->handle(7, $this->content(date: '2026-11-09'));
            $this->fail('The application must enforce the domain date rule.');
        } catch (InvalidEntry $exception) {
            $this->assertArrayHasKey('occurred_on', $exception->errors);
            $this->assertSame(0, $this->entriesRepository->count());
            $this->assertCount(0, $this->domainEventOutbox->events);
        }
    }

    public function test_a_failed_revision_leaves_the_saved_entry_unchanged(): void
    {
        $entry = $this->recordEntryCommand()->handle(7, $this->content());
        $id = $entry->id();
        $this->assertNotNull($id);

        try {
            (new ReviseEntryCommand($this->entriesRepository, $this->clock, $this->unitOfWork()))->handle(7, $id, $this->content(date: '2026-11-09'));
            $this->fail('The future-dated revision must fail.');
        } catch (InvalidEntry) {
            $this->assertSame('A meaningful day', $this->entriesRepository->getOwned(7, $id)->content()->title);
            $this->assertSame('2026-11-08', $this->entriesRepository->getOwned(7, $id)->content()->occurredOn->value);
        }
    }

    #[DataProvider('privateOperations')]
    public function test_use_cases_do_not_expose_or_modify_another_owners_entry(string $operation): void
    {
        $entry = $this->recordEntryCommand()->handle(7, $this->content());
        $id = $entry->id();
        $this->assertNotNull($id);

        try {
            match ($operation) {
                'find' => (new FindEntryQuery($this->entriesRepository))->handle(8, $id),
                'revise' => (new ReviseEntryCommand($this->entriesRepository, $this->clock, $this->unitOfWork()))->handle(8, $id, $this->content('Changed')),
                'forget' => (new ForgetEntryCommand($this->entriesRepository, $this->unitOfWork()))->handle(8, $id),
            };
            $this->fail('Other owners must not have access to this entry.');
        } catch (EntryNotFound) {
            $this->assertSame(1, $this->entriesRepository->count());
            $this->assertSame('A meaningful day', $this->entriesRepository->getOwned(7, $id)->content()->title);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function privateOperations(): iterable
    {
        yield 'read' => ['find'];
        yield 'update' => ['revise'];
        yield 'delete' => ['forget'];
    }

    private function content(string $title = 'A meaningful day', string $date = '2026-11-08'): EntryContent
    {
        return new EntryContent($title, EntryKind::Event, JournalDate::fromString($date), 'A moment I want to remember.');
    }

    private function recordEntryCommand(): RecordEntryCommand
    {
        return new RecordEntryCommand(
            clock: $this->clock,
            unitOfWork: $this->unitOfWork(),
        );
    }

    private function unitOfWork(): UnitOfWork
    {
        return new UnitOfWork(
            entriesRepository: $this->entriesRepository,
            domainEventOutbox: $this->domainEventOutbox,
            transactionManager: $this->transactionManager,
        );
    }
}
