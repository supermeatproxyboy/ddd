<?php

namespace Tests\Feature;

use App\Journal\Domain\Entries\EntryKind;
use App\Journal\Domain\Entries\JournalDate;
use App\Journal\Domain\JournalClock;
use App\Journal\Infrastructure\Persistence\Eloquent\EntryRecord;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_journal_action_requires_authentication(): void
    {
        $entry = EntryRecord::factory()->create();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('entries.create'))->assertRedirect(route('login'));
        $this->get(route('entries.edit', $entry))->assertRedirect(route('login'));
        $this->post(route('entries.store'), $this->payload())->assertRedirect(route('login'));
        $this->put(route('entries.update', $entry), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('entries.destroy', $entry))->assertRedirect(route('login'));
    }

    public function test_unverified_accounts_must_verify_before_using_the_journal(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('dashboard'))->assertRedirect(route('verification.notice'));
        $this->post(route('entries.store'), $this->payload())
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_create_form_starts_with_today_and_no_entry(): void
    {
        $this->actingAs(User::factory()->create())->get(route('entries.create'))
            ->assertInertia(fn (Assert $page) => $page->component('entries/Form')
                ->where('entry', null)->where('today', today()->toDateString()));
    }

    public function test_entries_are_saved_to_the_authenticated_account_even_if_an_owner_is_supplied(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $payload = [...$this->payload(), 'user_id' => $other->id];

        $this->actingAs($user)->post(route('entries.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('entries', [
            'user_id' => $user->id,
            'title' => $payload['title'],
            'kind' => 'accomplishment',
            'reflection' => $payload['reflection'],
        ]);
        $this->assertDatabaseCount('entries', 1);
        $this->assertDatabaseMissing('entries', ['user_id' => $other->id]);
    }

    public function test_invalid_and_future_dated_entries_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('entries.store'), [
            'title' => str_repeat('a', 161),
            'kind' => 'unknown',
            'occurred_on' => today()->addDay()->toDateString(),
            'body' => '',
            'reflection' => str_repeat('a', 5001),
        ])->assertSessionHasErrors(['title', 'kind', 'occurred_on', 'body', 'reflection']);

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_domain_violations_are_translated_to_form_errors(): void
    {
        $this->app->instance(JournalClock::class, new class implements JournalClock
        {
            public function today(): JournalDate
            {
                return JournalDate::fromString(today()->subDay()->toDateString());
            }

            public function now(): DateTimeImmutable
            {
                return now()->toDateTimeImmutable();
            }
        });

        $this->actingAs(User::factory()->create())->post(route('entries.store'), [
            ...$this->payload(),
            'occurred_on' => today()->toDateString(),
        ])->assertSessionHasErrors('occurred_on');

        $this->assertDatabaseCount('entries', 0);
    }

    public function test_edit_form_and_update_preserve_the_original_event_date(): void
    {
        $user = User::factory()->create();
        $entry = EntryRecord::factory()->for($user)->create(['occurred_on' => '2025-01-05']);

        $this->actingAs($user)->get(route('entries.edit', $entry))
            ->assertInertia(fn (Assert $page) => $page->component('entries/Form')
                ->where('entry.id', $entry->id)->where('entry.occurred_on', '2025-01-05'));

        $this->put(route('entries.update', $entry), [
            ...$this->payload(),
            'title' => 'A milestone worth remembering',
            'kind' => 'achievement',
            'occurred_on' => '2025-01-05',
            'reflection' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $entry->refresh();
        $this->assertSame('A milestone worth remembering', $entry->title);
        $this->assertSame(EntryKind::Achievement, $entry->kind);
        $this->assertSame('2025-01-05', $entry->occurred_on->toDateString());
        $this->assertNull($entry->reflection);
    }

    public function test_an_owner_can_delete_an_entry(): void
    {
        $user = User::factory()->create();
        $entry = EntryRecord::factory()->for($user)->create();

        $this->actingAs($user)->delete(route('entries.destroy', $entry))->assertRedirect(route('dashboard'));

        $this->assertModelMissing($entry);
    }

    public function test_other_peoples_entries_cannot_be_read_changed_or_deleted(): void
    {
        $entry = EntryRecord::factory()->create(['title' => 'Private memory']);
        $this->actingAs(User::factory()->create())->get(route('entries.edit', $entry))->assertNotFound();
        $this->put(route('entries.update', $entry), $this->payload())->assertNotFound();
        $this->delete(route('entries.destroy', $entry))->assertNotFound();

        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'title' => 'Private memory']);
    }

    public function test_timeline_and_statistics_only_include_the_owners_entries_in_date_order(): void
    {
        $user = User::factory()->create();
        $old = EntryRecord::factory()->for($user)->create(['occurred_on' => '2025-01-01']);
        $new = EntryRecord::factory()->for($user)->create(['occurred_on' => '2025-01-02', 'kind' => EntryKind::Accomplishment]);
        $newest = EntryRecord::factory()->for($user)->create(['occurred_on' => '2025-01-02', 'kind' => EntryKind::Achievement]);
        EntryRecord::factory()->create(['occurred_on' => '2025-01-03', 'kind' => EntryKind::Achievement]);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->has('entries.data', 3)
            ->where('entries.data.0.id', $newest->id)
            ->where('entries.data.1.id', $new->id)
            ->where('entries.data.2.id', $old->id)
            ->where('entries.data.0.occurred_on', '2025-01-02')
            ->where('entries.data.0.kind', 'achievement')
            ->where('stats.total', 3)->where('stats.days', 2)->where('stats.milestones', 2));
    }

    public function test_search_month_and_kind_filters_combine_without_leaking_other_accounts(): void
    {
        $user = User::factory()->create();
        $match = EntryRecord::factory()->for($user)->create([
            'reflection' => 'I found the courage to try.',
            'kind' => EntryKind::Achievement,
            'occurred_on' => '2025-02-02',
        ]);
        EntryRecord::factory()->for($user)->create(['title' => 'Courage', 'occurred_on' => '2025-01-02']);
        EntryRecord::factory()->for($user)->create(['title' => 'Courage', 'occurred_on' => '2025-02-01']);
        EntryRecord::factory()->create(['reflection' => 'Courage', 'kind' => EntryKind::Achievement, 'occurred_on' => '2025-02-02']);

        $this->actingAs($user)->get(route('dashboard', ['q' => 'courage', 'kind' => 'achievement', 'month' => '2025-02']))
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)
                ->where('entries.data.0.id', $match->id)
                ->where('filters.q', 'courage')->where('filters.month', '2025-02')
                ->where('stats.total', 3));
    }

    public function test_pagination_retains_filters_and_empty_searches_leave_the_journal_unchanged(): void
    {
        $user = User::factory()->create();
        EntryRecord::factory()->for($user)->count(13)->create(['title' => 'A small victory']);

        $this->actingAs($user)->get(route('dashboard', ['q' => 'victory']))
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 12)
                ->where('entries.total', 13)->where('entries.last_page', 2)
                ->where('entries.next_page_url', fn (string $url) => str_contains($url, 'q=victory') && str_contains($url, 'page=2')));

        $this->get(route('dashboard', ['q' => 'nonexistent memory']))
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0)->where('stats.total', 13));
        $this->assertDatabaseCount('entries', 13);
    }

    public function test_deleting_an_account_also_deletes_its_journal_entries(): void
    {
        $user = User::factory()->create();
        $entry = EntryRecord::factory()->for($user)->create();
        $other = EntryRecord::factory()->create();

        $user->delete();

        $this->assertModelMissing($entry);
        $this->assertModelExists($other);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'title' => 'Finished my first personal project',
            'kind' => 'accomplishment',
            'occurred_on' => today()->subDay()->toDateString(),
            'body' => 'I took an idea and made something I can actually use.',
            'reflection' => 'Following through feels good.',
        ];
    }
}
