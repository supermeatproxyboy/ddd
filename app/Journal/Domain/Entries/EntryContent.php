<?php

namespace App\Journal\Domain\Entries;

use App\Shared\Domain\ValueObject;

final readonly class EntryContent extends ValueObject
{
    public const TITLE_LIMIT = 160;

    public const BODY_LIMIT = 20000;

    public const REFLECTION_LIMIT = 5000;

    public string $title;

    public string $body;

    public ?string $reflection;

    public function __construct(
        string $title,
        public EntryKind $kind,
        public JournalDate $occurredOn,
        string $body,
        ?string $reflection = null,
    ) {
        $title = trim($title);
        $body = trim($body);
        $reflection = $reflection === null ? null : trim($reflection);
        $errors = [];

        if ($title === '' || mb_strlen($title) > self::TITLE_LIMIT) {
            $errors['title'] = 'Give the moment a title of 1 to '.self::TITLE_LIMIT.' characters.';
        }

        if ($body === '' || mb_strlen($body) > self::BODY_LIMIT) {
            $errors['body'] = 'Write a story of 1 to '.self::BODY_LIMIT.' characters.';
        }

        if ($reflection !== null && mb_strlen($reflection) > self::REFLECTION_LIMIT) {
            $errors['reflection'] = 'Keep your reflection within '.self::REFLECTION_LIMIT.' characters.';
        }

        if ($errors !== []) {
            throw new InvalidEntry($errors);
        }

        $this->title = $title;
        $this->body = $body;
        $this->reflection = $reflection === '' ? null : $reflection;
    }

    /** @return array<string, int|string|null> */
    protected function equalityComponents(): array
    {
        return [
            'title' => $this->title,
            'kind' => $this->kind->value,
            'occurred_on' => $this->occurredOn->value,
            'body' => $this->body,
            'reflection' => $this->reflection,
        ];
    }
}
