<?php

namespace Tests\Doubles;

use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryNotFound;
use App\Journal\Domain\Entries\EntryRepository;

final class InMemoryEntryRepository implements EntryRepository
{
    /** @var array<int, Entry> */
    private array $entries = [];

    private int $nextId = 1;

    public function getOwned(int $ownerId, int $entryId): Entry
    {
        $entry = $this->entries[$entryId] ?? null;

        if ($entry === null || $entry->ownerId() !== $ownerId) {
            throw new EntryNotFound;
        }

        return clone $entry;
    }

    public function save(Entry $entry): Entry
    {
        if ($entry->id() !== null) {
            $this->getOwned($entry->ownerId(), $entry->id());
        }

        $id = $entry->id() ?? $this->nextId++;
        $saved = Entry::reconstitute($id, $entry->ownerId(), $entry->content());
        $this->entries[$id] = clone $saved;

        return $saved;
    }

    public function delete(Entry $entry): void
    {
        $this->getOwned($entry->ownerId(), $entry->id() ?? 0);
        unset($this->entries[$entry->id()]);
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
