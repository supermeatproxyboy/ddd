<?php

namespace App\Journal\Application\UnitOfWork;

use App\Journal\Domain\Entries\Entry;
use LogicException;

final readonly class CommitResult
{
    /** @param list<array{original: Entry, persisted: Entry}> $persistedEntries */
    public function __construct(
        private array $persistedEntries,
    ) {}

    public function persistedEntry(Entry $originalEntry): Entry
    {
        foreach ($this->persistedEntries as $entry) {
            if ($entry['original'] === $originalEntry) {
                return $entry['persisted'];
            }
        }

        throw new LogicException('This entry was not persisted by this commit.');
    }
}
