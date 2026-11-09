<?php

namespace App\Journal\Domain\Entries;

interface EntryRepository
{
    /** @throws EntryNotFound When the entry does not exist or belongs to another owner. */
    public function getOwned(int $ownerId, int $entryId): Entry;

    /** Returns the persisted aggregate, including its assigned identity. */
    public function save(Entry $entry): Entry;

    public function delete(Entry $entry): void;
}
