<?php

namespace App\Journal\Domain\Entries;

enum EntryKind: string
{
    case Event = 'event';
    case Accomplishment = 'accomplishment';
    case Achievement = 'achievement';
}
