<?php

namespace Tests\Unit\Shared;

use App\Shared\Domain\Entity;
use App\Shared\Domain\ValueObject;
use Error;
use PHPUnit\Framework\TestCase;

class DomainAbstractionsTest extends TestCase
{
    public function test_value_objects_compare_values_instead_of_object_references(): void
    {
        $first = $this->value('same value');
        $second = $this->value('same value');

        $this->assertNotSame($first, $second);
        $this->assertTrue($first->equals($second));
        $this->assertTrue($second->equals($first));
        $this->assertTrue($first->equals($first));
        $this->assertFalse($first->equals($this->value('different value')));
    }

    public function test_different_value_object_types_are_not_equal_even_with_identical_components(): void
    {
        $first = $this->value('same value');
        $otherType = new readonly class('same value') extends ValueObject
        {
            public function __construct(public string $value) {}

            protected function equalityComponents(): array
            {
                return ['value' => $this->value];
            }
        };

        $this->assertFalse($first->equals($otherType));
        $this->assertFalse($otherType->equals($first));
    }

    public function test_value_object_properties_cannot_be_reassigned(): void
    {
        $value = $this->value('original');
        $this->expectException(Error::class);

        $value->value = 'changed';
    }

    public function test_entities_compare_identity_even_when_their_state_differs(): void
    {
        $first = $this->entity(7, 'Original state');
        $second = $this->entity(7, 'Revised state');

        $this->assertNotSame($first, $second);
        $this->assertTrue($first->sameIdentityAs($second));
        $this->assertTrue($second->sameIdentityAs($first));
        $this->assertFalse($first->sameIdentityAs($this->entity(8)));
    }

    public function test_two_unsaved_entities_do_not_share_an_identity(): void
    {
        $first = $this->entity(null);
        $second = $this->entity(null);

        $this->assertTrue($first->sameIdentityAs($first));
        $this->assertFalse($first->sameIdentityAs($second));
        $this->assertFalse($first->sameIdentityAs($this->entity(7)));
    }

    public function test_entity_identity_comparison_does_not_coerce_scalar_types(): void
    {
        $this->assertFalse($this->entity(7)->sameIdentityAs($this->entity('7')));
    }

    public function test_entities_can_use_value_object_identifiers(): void
    {
        $first = $this->entity($this->value('entry-7'));
        $second = $this->entity($this->value('entry-7'));

        $this->assertTrue($first->sameIdentityAs($second));
        $this->assertFalse($first->sameIdentityAs($this->entity($this->value('entry-8'))));
        $this->assertFalse($first->sameIdentityAs($this->entity('entry-7')));
        $this->assertFalse($this->entity('entry-7')->sameIdentityAs($first));
    }

    public function test_different_entity_types_do_not_share_identity(): void
    {
        $first = $this->entity(7);
        $otherType = new class extends Entity
        {
            public function id(): int
            {
                return 7;
            }
        };

        $this->assertFalse($first->sameIdentityAs($otherType));
        $this->assertFalse($otherType->sameIdentityAs($first));
    }

    private function value(string $value): ValueObject
    {
        return new readonly class($value) extends ValueObject
        {
            public function __construct(public string $value) {}

            protected function equalityComponents(): array
            {
                return ['value' => $this->value];
            }
        };
    }

    private function entity(int|string|ValueObject|null $id, string $description = 'An entity'): Entity
    {
        return new class($id, $description) extends Entity
        {
            public function __construct(
                private readonly int|string|ValueObject|null $identity,
                public string $description,
            ) {}

            public function id(): int|string|ValueObject|null
            {
                return $this->identity;
            }
        };
    }
}
