<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntryRequest;
use App\Http\Requests\JournalIndexRequest;
use App\Journal\Application\Commands\ForgetEntryCommand;
use App\Journal\Application\Commands\RecordEntryCommand;
use App\Journal\Application\Commands\ReviseEntryCommand;
use App\Journal\Application\Queries\EntryView;
use App\Journal\Application\Queries\FindEntryQuery;
use App\Journal\Application\Queries\ReadJournalQuery;
use App\Journal\Domain\JournalClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class EntryController extends Controller
{
    public function index(
        JournalIndexRequest $request,
        ReadJournalQuery $readJournalQuery,
    ): Response {
        $filters = $request->filters();
        $journal = $readJournalQuery->handle(
            ownerId: $request->user()->id,
            filters: $filters,
            page: $request->integer('page', 1),
        );
        $entries = new LengthAwarePaginator(
            array_map(fn (EntryView $entry) => $entry->toArray(), $journal->entries),
            $journal->total,
            $journal->perPage,
            $journal->page,
            ['path' => route('dashboard')],
        );

        return Inertia::render('Dashboard', [
            'entries' => $entries->withQueryString(),
            'filters' => [
                'q' => $filters->search,
                'kind' => $filters->kind === null ? '' : $filters->kind->value,
                'month' => $filters->month ?? '',
            ],
            'stats' => [
                'total' => $journal->statistics->total,
                'days' => $journal->statistics->days,
                'milestones' => $journal->statistics->milestones,
            ],
        ]);
    }

    public function create(JournalClock $clock): Response
    {
        return Inertia::render('entries/Form', [
            'entry' => null,
            'today' => $clock->today()->value,
        ]);
    }

    public function store(
        EntryRequest       $request,
        RecordEntryCommand $recordEntryCommand,
    ): RedirectResponse
    {
        $recordEntryCommand->handle(
            ownerId: $request->user()->id,
            content: $request->content(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'A moment worth remembering. Saved.']);

        return to_route('dashboard');
    }

    public function edit(
        Request $request,
        string $entry,
        FindEntryQuery $findEntryQuery,
        JournalClock $clock,
    ): Response {
        $entryView = $findEntryQuery->handle(
            ownerId: $request->user()->id,
            entryId: (int) $entry,
        );

        return Inertia::render('entries/Form', [
            'entry' => $entryView->toArray(),
            'today' => $clock->today()->value,
        ]);
    }

    public function update(
        EntryRequest $request,
        string $entry,
        ReviseEntryCommand $reviseEntryCommand,
    ): RedirectResponse {
        $reviseEntryCommand->handle(
            ownerId: $request->user()->id,
            entryId: (int) $entry,
            content: $request->content(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your entry has been updated.']);

        return to_route('dashboard');
    }

    public function destroy(
        Request $request,
        string $entry,
        ForgetEntryCommand $forgetEntryCommand,
    ): RedirectResponse {
        $forgetEntryCommand->handle(
            ownerId: $request->user()->id,
            entryId: (int) $entry,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entry deleted.']);

        return to_route('dashboard');
    }
}
