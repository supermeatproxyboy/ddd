<?php

namespace App\Journal\Infrastructure\Persistence\Eloquent;

use App\Journal\Domain\Entries\Entry;
use App\Journal\Domain\Entries\EntryContent;
use App\Journal\Domain\Entries\EntryNotFound;
use App\Journal\Domain\Entries\EntryRepository;
use App\Journal\Domain\Entries\JournalDate;
use LogicException;

final class EloquentEntryRepository implements EntryRepository
{
    public function getOwned(int $ownerId, int $entryId): Entry
    {
        return $this->toDomain($this->findRecord($ownerId, $entryId));
    }

    public function save(Entry $entry): Entry
    {
        $record = $entry->id() === null
            ? new EntryRecord
            : $this->findRecord($entry->ownerId(), $entry->id());
        $content = $entry->content();
        $record->user_id = $entry->ownerId();
        $record->fill([
            'title' => $content->title,
            'kind' => $content->kind,
            'occurred_on' => $content->occurredOn->value,
            'body' => $content->body,
            'reflection' => $content->reflection,
        ])->save();

        return $this->toDomain($record);
    }

    public function delete(Entry $entry): void
    {
        $id = $entry->id() ?? throw new LogicException('An unsaved entry cannot be deleted.');
        $deleted = EntryRecord::query()->where('user_id', $entry->ownerId())->whereKey($id)->delete();

        if ($deleted === 0) {
            throw new EntryNotFound;
        }
    }

    private function findRecord(int $ownerId, int $entryId): EntryRecord
    {
        return EntryRecord::query()->where('user_id', $ownerId)->find($entryId) ?? throw new EntryNotFound;
    }

    private function toDomain(EntryRecord $record): Entry
    {
        return Entry::reconstitute($record->id, $record->user_id, new EntryContent(
            $record->title,
            $record->kind,
            JournalDate::fromString($record->occurred_on->toDateString()),
            $record->body,
            $record->reflection,
        ));
    }
}
