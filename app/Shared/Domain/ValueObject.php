<?php

namespace App\Shared\Domain;

abstract readonly class ValueObject
{
    final public function equals(self $other): bool
    {
        if ($this === $other) {
            return true;
        }

        return $this::class === $other::class
            && $this->equalityComponents() === $other->equalityComponents();
    }

    /** @return array<string, bool|float|int|string|null> */
    abstract protected function equalityComponents(): array;
}
