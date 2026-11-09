<?php

namespace App\Shared\Domain;

abstract class Entity
{
    abstract public function id(): int|string|ValueObject|null;

    final public function sameIdentityAs(self $other): bool
    {
        if ($this::class !== $other::class) {
            return false;
        }

        if ($this === $other) {
            return true;
        }

        $identity = $this->id();
        $otherIdentity = $other->id();

        if ($identity === null || $otherIdentity === null) {
            return false;
        }

        if ($identity instanceof ValueObject) {
            return $otherIdentity instanceof ValueObject && $identity->equals($otherIdentity);
        }

        return $identity === $otherIdentity;
    }
}
