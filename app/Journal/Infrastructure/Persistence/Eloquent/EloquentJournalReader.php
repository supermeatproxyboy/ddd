<?php

namespace App\Journal\Infrastructure\Persistence\Eloquent;

use App\Journal\Application\Queries\EntryView;
use App\Journal\Application\Queries\JournalFilters;
use App\Journal\Application\Queries\JournalPage;
use App\Journal\Application\Queries\JournalReader;
use App\Journal\Application\Queries\JournalStatistics;
use App\Journal\Domain\Entries\EntryKind;
use InvalidArgumentException;

final class EloquentJournalReader implements JournalReader
{
    public function read(int $ownerId, JournalFilters $filters, int $page = 1): JournalPage
    {
        if ($page < 1) {
            throw new InvalidArgumentException('The page number must be positive.');
        }

        $journal = EntryRecord::query()->where('user_id', $ownerId);
        $query = clone $journal;

        if ($filters->search !== '') {
            $search = '%'.$filters->search.'%';
            $query->where(function ($query) use ($search) {
                $query->whereLike('title', $search)
                    ->orWhereLike('body', $search)
                    ->orWhereLike('reflection', $search);
            });
        }

        if ($filters->kind !== null) {
            $query->where('kind', $filters->kind);
        }

        if ($filters->month !== null) {
            $query->whereYear('occurred_on', substr($filters->month, 0, 4))
                ->whereMonth('occurred_on', substr($filters->month, 5, 2));
        }

        $results = $query->orderByDesc('occurred_on')->orderByDesc('id')->paginate(12, ['*'], 'page', $page);
        $entries = $results->getCollection()->map(fn (EntryRecord $entry) => new EntryView(
            $entry->id,
            $entry->title,
            $entry->kind->value,
            $entry->occurred_on->toDateString(),
            $entry->body,
            $entry->reflection,
        ));

        return new JournalPage(
            array_values($entries->all()),
            $results->total(),
            $page,
            $results->perPage(),
            new JournalStatistics(
                (clone $journal)->count(),
                (clone $journal)->distinct()->count('occurred_on'),
                (clone $journal)->whereIn('kind', [EntryKind::Accomplishment, EntryKind::Achievement])->count(),
            ),
        );
    }
}
