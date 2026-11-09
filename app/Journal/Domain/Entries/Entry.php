<?php

namespace App\Journal\Domain\Entries;

use App\Shared\Domain\AggregateRoot;
use InvalidArgumentException;

final class Entry extends AggregateRoot
{
    public const AGGREGATE_TYPE = 'journal_entry';

    private function __construct(
        private readonly ?int $id,
        private readonly int $ownerId,
        private EntryContent $content,
    ) {
        if ($ownerId < 1 || ($id !== null && $id < 1)) {
            throw new InvalidArgumentException('Entry and owner identities must be positive.');
        }
    }

    public static function record(int $ownerId, EntryContent $content, JournalDate $today): self
    {
        self::ensureMomentHasHappened($content, $today);

        return new self(null, $ownerId, $content);
    }

    public static function reconstitute(int $id, int $ownerId, EntryContent $content): self
    {
        return new self($id, $ownerId, $content);
    }

    public function revise(EntryContent $content, JournalDate $today): void
    {
        self::ensureMomentHasHappened($content, $today);

        $this->content = $content;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function aggregateType(): string
    {
        return self::AGGREGATE_TYPE;
    }

    public function ownerId(): int
    {
        return $this->ownerId;
    }

    public function content(): EntryContent
    {
        return $this->content;
    }

    private static function ensureMomentHasHappened(EntryContent $content, JournalDate $today): void
    {
        if ($content->occurredOn->isAfter($today)) {
            throw new InvalidEntry(['occurred_on' => 'A journal moment cannot be in the future.']);
        }
    }
}
