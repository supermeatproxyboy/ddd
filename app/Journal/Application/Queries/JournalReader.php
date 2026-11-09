<?php

namespace App\Journal\Application\Queries;

interface JournalReader
{
    public function read(int $ownerId, JournalFilters $filters, int $page = 1): JournalPage;
}
