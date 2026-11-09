<?php

namespace App\Journal\Application\Queries;

use App\Journal\Domain\Entries\Entry;
use LogicException;

final readonly class EntryView
{
    public function __construct(
        public int $id,
        public string $title,
        public string $kind,
        public string $occurredOn,
        public string $body,
        public ?string $reflection,
    ) {}

    public static function fromEntry(Entry $entry): self
    {
        $content = $entry->content();

        return new self(
            $entry->id() ?? throw new LogicException('An entry must be saved before it is displayed.'),
            $content->title,
            $content->kind->value,
            $content->occurredOn->value,
            $content->body,
            $content->reflection,
        );
    }

    /** @return array{id: int, title: string, kind: string, occurred_on: string, body: string, reflection: string|null} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'kind' => $this->kind,
            'occurred_on' => $this->occurredOn,
            'body' => $this->body,
            'reflection' => $this->reflection,
        ];
    }
}
