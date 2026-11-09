<?php

namespace App\Providers;

use App\Journal\Application\Outbox\DomainEventOutbox;
use App\Journal\Application\Queries\JournalReader;
use App\Journal\Application\Transactions\TransactionManager;
use App\Journal\Application\UnitOfWork\UnitOfWork;
use App\Journal\Domain\Entries\EntryRepository;
use App\Journal\Domain\JournalClock;
use App\Journal\Infrastructure\Outbox\DatabaseDomainEventOutbox;
use App\Journal\Infrastructure\Persistence\Eloquent\EloquentEntryRepository;
use App\Journal\Infrastructure\Persistence\Eloquent\EloquentJournalReader;
use App\Journal\Infrastructure\Time\LaravelJournalClock;
use App\Journal\Infrastructure\Transactions\LaravelTransactionManager;
use Illuminate\Support\ServiceProvider;

class JournalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EntryRepository::class, EloquentEntryRepository::class);
        $this->app->bind(JournalReader::class, EloquentJournalReader::class);
        $this->app->bind(JournalClock::class, LaravelJournalClock::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(DomainEventOutbox::class, DatabaseDomainEventOutbox::class);
        $this->app->bind(UnitOfWork::class, fn () => new UnitOfWork(
            entriesRepository: $this->app->make(EntryRepository::class),
            domainEventOutbox: $this->app->make(DomainEventOutbox::class),
            transactionManager: $this->app->make(TransactionManager::class),
        ));
    }
}
