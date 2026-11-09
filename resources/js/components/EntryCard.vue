<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Check,
    Pencil,
    Sparkles,
    Trash2,
    Trophy,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/entries';
import { entryKinds } from '@/types/entry';
import type { Entry } from '@/types/entry';

const props = defineProps<{ entry: Entry }>();
defineEmits<{ remove: [entry: Entry] }>();
const expanded = ref(false);
const kind = computed(() =>
    entryKinds.find((kind) => kind.value === props.entry.kind)!,
);
const icons = { event: Sparkles, accomplishment: Check, achievement: Trophy };
</script>

<template>
    <article
        class="rounded-2xl border border-border/80 bg-card p-5 shadow-xs sm:p-6"
    >
        <div class="flex items-start justify-between gap-4">
            <span
                class="inline-flex items-center gap-1.5 text-xs font-medium"
                :class="{
                    'text-sky-700 dark:text-sky-300': entry.kind === 'event',
                    'text-emerald-700 dark:text-emerald-300':
                        entry.kind === 'accomplishment',
                    'text-amber-700 dark:text-amber-300':
                        entry.kind === 'achievement',
                }"
            >
                <component
                    :is="icons[entry.kind]"
                    class="size-3.5"
                    aria-hidden="true"
                />
                {{ kind.label }}
            </span>
            <div class="-mt-1.5 -mr-2 flex gap-1">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link
                        :href="edit(entry.id)"
                        :aria-label="`Edit ${entry.title}`"
                        ><Pencil class="size-3.5"
                    /></Link>
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    class="text-muted-foreground hover:text-destructive"
                    :aria-label="`Delete ${entry.title}`"
                    @click="$emit('remove', entry)"
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>
        </div>
        <h3
            class="mt-3 text-xl leading-snug font-semibold tracking-tight break-words"
        >
            {{ entry.title }}
        </h3>
        <p
            class="mt-3 text-sm leading-7 wrap-anywhere whitespace-pre-wrap text-muted-foreground"
            :class="{ 'line-clamp-3': !expanded }"
        >
            {{ entry.body }}
        </p>
        <div
            v-if="expanded && entry.reflection"
            class="mt-5 border-l-2 border-primary/30 pl-4"
        >
            <p
                class="mb-1 text-xs font-medium tracking-wide text-primary uppercase"
            >
                Why it mattered
            </p>
            <p class="text-sm leading-7 wrap-anywhere whitespace-pre-wrap">
                {{ entry.reflection }}
            </p>
        </div>
        <button
            type="button"
            class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            {{ expanded ? 'Show less' : 'Read the full moment'
            }}<ArrowUpRight
                v-if="!expanded"
                class="size-3"
                aria-hidden="true"
            />
        </button>
    </article>
</template>
