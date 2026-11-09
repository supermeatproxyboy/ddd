<?php

namespace App\Journal\Domain\Events;

use App\Shared\Domain\ValueObject;

final readonly class DomainEventId extends ValueObject
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return new self(sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ));
    }

    /** @return array<string, string> */
    protected function equalityComponents(): array
    {
        return ['value' => $this->value];
    }
}
