<?php

namespace App\Journal\Application\Queries;

use App\Journal\Domain\Entries\EntryKind;

final readonly class JournalFilters
{
    public function __construct(
        public string $search = '',
        public ?EntryKind $kind = null,
        public ?string $month = null,
    ) {}
}
