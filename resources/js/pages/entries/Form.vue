<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, LockKeyhole, Sparkles, Trophy } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { store, update } from '@/routes/entries';
import { entryKinds } from '@/types/entry';
import type { Entry, EntryKind } from '@/types/entry';

defineOptions({
    layout: { breadcrumbs: [{ title: 'My journal', href: dashboard() }] },
});
const props = defineProps<{ entry: Entry | null; today: string }>();
const form = useForm({
    title: props.entry?.title ?? '',
    kind: props.entry?.kind ?? ('event' as EntryKind),
    occurred_on: props.entry?.occurred_on ?? props.today,
    body: props.entry?.body ?? '',
    reflection: props.entry?.reflection ?? '',
});
const icons = { event: Sparkles, accomplishment: Check, achievement: Trophy };

function submit(): void {
    if (props.entry) {
        form.put(update(props.entry.id).url);
    } else {
        form.post(store().url);
    }
}
</script>

<template>
    <Head :title="entry ? 'Edit moment' : 'A new moment'" />
    <div class="mx-auto w-full max-w-3xl px-5 py-8 sm:px-10 sm:py-12">
        <Link
            :href="dashboard()"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
            ><ArrowLeft class="size-4" /> Back to my journal</Link
        >
        <header class="mt-8 mb-9">
            <p class="journal-eyebrow">
                {{
                    entry ? 'Keep the details' : 'Make a little space for today'
                }}
            </p>
            <h1
                class="mt-3 font-serif text-4xl leading-tight tracking-tight sm:text-5xl"
            >
                {{
                    entry ? 'Revisit this moment.' : 'What’s worth remembering?'
                }}
            </h1>
            <p class="mt-4 max-w-lg text-sm leading-7 text-muted-foreground">
                A big milestone or a small, meaningful moment. If it matters to
                you, it belongs here.
            </p>
        </header>
        <form class="space-y-7" @submit.prevent="submit">
            <fieldset>
                <legend class="mb-3 text-sm font-medium">
                    What kind of moment?
                </legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label
                        v-for="kind in entryKinds"
                        :key="kind.value"
                        class="relative cursor-pointer"
                    >
                        <input
                            v-model="form.kind"
                            type="radio"
                            name="kind"
                            :value="kind.value"
                            class="peer sr-only"
                        />
                        <span
                            class="flex h-full flex-col gap-2 rounded-xl border bg-card p-4 transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2"
                        >
                            <component
                                :is="icons[kind.value]"
                                class="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <span class="text-sm font-medium">{{
                                kind.label
                            }}</span>
                            <span
                                class="text-xs leading-5 text-muted-foreground"
                                >{{ kind.description }}</span
                            >
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.kind" class="mt-2" />
            </fieldset>
            <div class="grid gap-2">
                <Label for="title"
                    >Give it a title
                    <span class="text-muted-foreground">(required)</span></Label
                >
                <Input
                    id="title"
                    v-model="form.title"
                    required
                    maxlength="160"
                    placeholder="Something you’ll love looking back on"
                    class="h-11 bg-card"
                    :aria-invalid="!!form.errors.title"
                    aria-describedby="title-error"
                />
                <InputError id="title-error" :message="form.errors.title" />
            </div>
            <div class="grid max-w-xs gap-2">
                <Label for="occurred-on"
                    >When did it happen?
                    <span class="text-muted-foreground">(required)</span></Label
                >
                <Input
                    id="occurred-on"
                    v-model="form.occurred_on"
                    type="date"
                    required
                    :max="today"
                    class="h-11 bg-card"
                    :aria-invalid="!!form.errors.occurred_on"
                    aria-describedby="date-error"
                />
                <InputError
                    id="date-error"
                    :message="form.errors.occurred_on"
                />
            </div>
            <div class="grid gap-2">
                <Label for="body"
                    >The story
                    <span class="text-muted-foreground">(required)</span></Label
                >
                <textarea
                    id="body"
                    v-model="form.body"
                    required
                    maxlength="20000"
                    rows="7"
                    class="journal-field resize-y bg-card leading-7"
                    placeholder="What happened? Where were you? Who was there? Capture the details you don’t want to forget."
                    :aria-invalid="!!form.errors.body"
                    aria-describedby="body-error"
                />
                <InputError id="body-error" :message="form.errors.body" />
            </div>
            <div class="grid gap-2">
                <Label for="reflection"
                    >Why did it matter?
                    <span class="text-muted-foreground">(optional)</span></Label
                >
                <textarea
                    id="reflection"
                    v-model="form.reflection"
                    maxlength="5000"
                    rows="4"
                    class="journal-field resize-y bg-card leading-7"
                    placeholder="How did it make you feel? What did you learn? What would you tell your future self?"
                    :aria-invalid="!!form.errors.reflection"
                    aria-describedby="reflection-error"
                />
                <InputError
                    id="reflection-error"
                    :message="form.errors.reflection"
                />
            </div>
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-t pt-6"
            >
                <p
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <LockKeyhole class="size-3.5" /> Just for you. Only your
                    account can see this.
                </p>
                <div class="flex gap-3">
                    <Button variant="ghost" as-child
                        ><Link :href="dashboard()">Cancel</Link></Button
                    >
                    <Button type="submit" :disabled="form.processing">{{
                        form.processing
                            ? 'Saving…'
                            : entry
                              ? 'Save changes'
                              : 'Save this moment'
                    }}</Button>
                </div>
            </div>
        </form>
    </div>
</template>
