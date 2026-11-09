<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CalendarDays,
    LockKeyhole,
    Plus,
    Search,
    Sparkles,
    Trophy,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EntryCard from '@/components/EntryCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { create, destroy } from '@/routes/entries';
import { entryKinds, formatEntryDate } from '@/types/entry';
import type { Entry, EntryKind, EntryPage } from '@/types/entry';

defineOptions({
    layout: { breadcrumbs: [{ title: 'My journal', href: dashboard() }] },
});
type Filters = { q: string; kind: EntryKind | ''; month: string };
const props = defineProps<{
    entries: EntryPage;
    filters: Filters;
    stats: { total: number; days: number; milestones: number };
}>();
const search = ref(props.filters.q);
const month = ref(props.filters.month);
const kind = ref(props.filters.kind);
const deleting = ref<Entry | null>(null);
const deleteBusy = ref(false);
const filtered = computed(() =>
    Boolean(props.filters.q || props.filters.kind || props.filters.month),
);
const groups = computed(() => {
    const dates = new Map<string, Entry[]>();
    for (const entry of props.entries.data) {
        const group = dates.get(entry.occurred_on) ?? [];
        group.push(entry);
        dates.set(entry.occurred_on, group);
    }
    return [...dates].map(([date, entries]) => ({ date, entries }));
});
watch(
    () => props.filters,
    (filters) => {
        search.value = filters.q;
        month.value = filters.month;
        kind.value = filters.kind;
    },
);

function applyFilters(): void {
    router.get(
        dashboard().url,
        { q: search.value, kind: kind.value, month: month.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}
function selectKind(value: EntryKind | ''): void {
    kind.value = value;
    applyFilters();
}
function clearFilters(): void {
    search.value = '';
    kind.value = '';
    month.value = '';
    applyFilters();
}
function deleteEntry(): void {
    if (!deleting.value || deleteBusy.value) return;
    deleteBusy.value = true;
    router.delete(destroy(deleting.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleting.value = null;
        },
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
}
</script>

<template>
    <Head title="My journal" />
    <div class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-10 sm:py-12">
        <header class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="journal-eyebrow">Your life, in moments</p>
                <h1
                    class="mt-3 font-serif text-4xl leading-tight tracking-tight sm:text-5xl"
                >
                    The days add up.<br /><span class="text-primary"
                        >Keep what matters.</span
                    >
                </h1>
                <p
                    class="mt-4 max-w-md text-sm leading-7 text-muted-foreground"
                >
                    A home for the things that happened, the progress you made,
                    and the moments that mattered.
                </p>
            </div>
            <Button size="lg" as-child
                ><Link :href="create()"
                    ><Plus class="size-4" /> Capture a moment</Link
                ></Button
            >
        </header>

        <section
            aria-label="Your journal at a glance"
            class="my-9 grid grid-cols-3 divide-x rounded-2xl border bg-card py-5 shadow-xs sm:my-12 sm:py-6"
        >
            <div
                v-for="stat in [
                    {
                        value: stats.total,
                        label: 'Moments saved',
                        icon: BookOpen,
                    },
                    {
                        value: stats.days,
                        label: 'Days remembered',
                        icon: CalendarDays,
                    },
                    {
                        value: stats.milestones,
                        label: 'Accomplishments & achievements',
                        icon: Trophy,
                    },
                ]"
                :key="stat.label"
                class="px-3 sm:px-6"
            >
                <component
                    :is="stat.icon"
                    class="mb-3 size-4 text-primary/70"
                    aria-hidden="true"
                />
                <p class="text-2xl font-medium tracking-tight sm:text-3xl">
                    {{ stat.value.toLocaleString() }}
                </p>
                <p
                    class="mt-1 text-[11px] leading-5 text-muted-foreground sm:text-xs"
                >
                    {{ stat.label }}
                </p>
            </div>
        </section>

        <section aria-labelledby="journal-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="journal-heading"
                    class="text-xl font-semibold tracking-tight"
                >
                    Your journal
                </h2>
                <span
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    ><LockKeyhole class="size-3.5" /> Private to you</span
                >
            </div>
            <form
                class="mt-5 flex flex-wrap items-end gap-3"
                @submit.prevent="applyFilters"
            >
                <div class="min-w-48 flex-1 space-y-2">
                    <Label for="search" class="text-xs text-muted-foreground"
                        >Find a memory</Label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                            aria-hidden="true"
                        /><Input
                            id="search"
                            v-model="search"
                            type="search"
                            maxlength="100"
                            placeholder="Search your moments…"
                            class="h-10 bg-card pl-9"
                        />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="month" class="text-xs text-muted-foreground"
                        >Jump to a month</Label
                    ><Input
                        id="month"
                        v-model="month"
                        type="month"
                        class="h-10 w-44 bg-card"
                    />
                </div>
                <Button type="submit" variant="outline" class="h-10 bg-card"
                    >Apply filters</Button
                >
                <Button
                    v-if="filtered"
                    type="button"
                    variant="ghost"
                    class="h-10"
                    @click="clearFilters"
                    >Clear</Button
                >
            </form>
            <div
                class="mt-5 mb-8 flex flex-wrap gap-2"
                role="group"
                aria-label="Filter by type"
            >
                <button
                    v-for="tab in [
                        { value: '', label: 'All moments' },
                        ...entryKinds,
                    ]"
                    :key="tab.value"
                    type="button"
                    :aria-pressed="kind === tab.value"
                    class="rounded-full border px-3.5 py-2 text-xs font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2"
                    :class="
                        kind === tab.value
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-transparent text-muted-foreground hover:border-border hover:bg-card'
                    "
                    @click="selectKind(tab.value as EntryKind | '')"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div
                v-if="!entries.data.length"
                class="rounded-2xl border border-dashed bg-card/60 px-5 py-16 text-center"
            >
                <span
                    class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/8 text-primary"
                    ><Sparkles class="size-5"
                /></span>
                <h3 class="mt-5 font-serif text-2xl">
                    {{
                        filtered
                            ? 'No moments found here.'
                            : 'Every story starts with a moment.'
                    }}
                </h3>
                <p
                    class="mx-auto mt-3 max-w-sm text-sm leading-7 text-muted-foreground"
                >
                    {{
                        filtered
                            ? 'Try a different search, month, or type. Your other memories are still in your journal.'
                            : 'A conversation that stayed with you. Something you finally finished. A first, a win, an ordinary day that felt special.'
                    }}
                </p>
                <Button
                    v-if="filtered"
                    variant="outline"
                    class="mt-6"
                    @click="clearFilters"
                    >Show all moments</Button
                >
                <Button v-else class="mt-6" as-child
                    ><Link :href="create()"
                        ><Plus class="size-4" /> Write your first moment</Link
                    ></Button
                >
            </div>

            <div v-else class="space-y-9">
                <section
                    v-for="group in groups"
                    :key="group.date"
                    :aria-label="formatEntryDate(group.date)"
                    class="grid gap-4 sm:grid-cols-[145px_1fr] sm:gap-7"
                >
                    <div class="pt-1">
                        <time
                            :datetime="group.date"
                            class="block text-sm font-medium"
                            >{{
                                formatEntryDate(group.date, {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                })
                            }}</time
                        >
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                formatEntryDate(group.date, { weekday: 'long' })
                            }}
                        </p>
                        <div
                            class="mt-4 hidden h-px w-8 bg-primary/25 sm:block"
                        />
                    </div>
                    <div class="space-y-4">
                        <EntryCard
                            v-for="entry in group.entries"
                            :key="entry.id"
                            :entry="entry"
                            @remove="deleting = $event"
                        />
                    </div>
                </section>
            </div>
            <nav
                v-if="entries.last_page > 1"
                aria-label="Journal pagination"
                class="mt-9 flex flex-wrap items-center justify-between gap-4 border-t pt-6"
            >
                <p class="text-xs text-muted-foreground">
                    {{ entries.from }}–{{ entries.to }} of
                    {{ entries.total }} moments · Page
                    {{ entries.current_page }} of {{ entries.last_page }}
                </p>
                <div class="flex gap-2">
                    <Button
                        v-if="entries.prev_page_url"
                        variant="outline"
                        size="sm"
                        as-child
                        ><Link :href="entries.prev_page_url"
                            ><ArrowLeft class="size-3.5" /> Newer</Link
                        ></Button
                    >
                    <Button
                        v-if="entries.next_page_url"
                        variant="outline"
                        size="sm"
                        as-child
                        ><Link :href="entries.next_page_url"
                            >Older <ArrowRight class="size-3.5" /></Link
                    ></Button>
                </div>
            </nav>
        </section>
        <footer
            class="mt-14 border-t pt-6 text-center font-serif text-sm text-muted-foreground"
        >
            You don’t have to remember everything. Just what matters.
        </footer>
    </div>
    <Dialog
        :open="deleting !== null"
        @update:open="
            (open) => {
                if (!open && !deleteBusy) deleting = null;
            }
        "
    >
        <DialogContent>
            <DialogHeader
                ><DialogTitle>Delete this moment?</DialogTitle
                ><DialogDescription
                    >“{{ deleting?.title }}” will be permanently removed from
                    your journal. This cannot be undone.</DialogDescription
                ></DialogHeader
            >
            <DialogFooter
                ><Button
                    variant="outline"
                    :disabled="deleteBusy"
                    @click="deleting = null"
                    >Keep it</Button
                ><Button
                    variant="destructive"
                    :disabled="deleteBusy"
                    @click="deleteEntry"
                    >{{ deleteBusy ? 'Deleting…' : 'Delete moment' }}</Button
                ></DialogFooter
            >
        </DialogContent>
    </Dialog>
</template>
