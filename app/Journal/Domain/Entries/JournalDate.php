<?php

namespace App\Journal\Domain\Entries;

use App\Shared\Domain\ValueObject;
use DateTimeImmutable;
use DateTimeZone;

final readonly class JournalDate extends ValueObject
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if (! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || str_starts_with($value, '0000')) {
            throw new InvalidEntry(['occurred_on' => 'Choose a valid calendar date.']);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidEntry(['occurred_on' => 'Choose a valid calendar date.']);
        }

        return new self($value);
    }

    public function isAfter(self $other): bool
    {
        return strcmp($this->value, $other->value) > 0;
    }

    /** @return array<string, string> */
    protected function equalityComponents(): array
    {
        return ['value' => $this->value];
    }
}
