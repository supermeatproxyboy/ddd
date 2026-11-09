<?php

namespace Database\Factories;

use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Infrastructure\Persistence\Eloquent\EntryRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EntryRecord> */
class EntryFactory extends Factory
{
    protected $model = EntryRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(5),
            'kind' => EntryKind::Event,
            'occurred_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'body' => fake()->paragraph(),
            'reflection' => null,
        ];
    }
}
