<?php

namespace App\Shared\Domain;

abstract class AggregateRoot extends Entity
{
    abstract public function aggregateType(): string;
}
