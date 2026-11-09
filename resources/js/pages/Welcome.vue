<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Check,
    LockKeyhole,
    Sparkles,
    Trophy,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head title="Keep what matters"
        ><meta
            name="description"
            content="DDD is your personal journal for meaningful events, everyday accomplishments, and life's achievements. Remember the moments that matter."
    /></Head>
    <div class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-7 sm:px-10"
        >
            <Link href="/" class="flex items-center gap-3" aria-label="DDD home"
                ><span
                    class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                    ><AppLogoIcon class="size-5" /></span
                ><span class="text-lg font-semibold tracking-tight"
                    >ddd<span class="text-primary">.</span></span
                ><span
                    class="hidden border-l pl-3 text-xs text-muted-foreground sm:block"
                    >A place for your days.</span
                ></Link
            >
            <nav aria-label="Main navigation" class="flex items-center gap-3">
                <Button v-if="$page.props.auth.user" as-child
                    ><Link :href="dashboard()"
                        >My journal <ArrowRight class="size-4" /></Link
                ></Button>
                <template v-else
                    ><Button variant="ghost" as-child
                        ><Link :href="login()">Log in</Link></Button
                    ><Button as-child
                        ><Link :href="register()"
                            >Start your journal
                            <ArrowRight class="size-4" /></Link></Button
                ></template>
            </nav>
        </header>
        <main class="mx-auto max-w-6xl px-6 pt-12 pb-16 sm:px-10 sm:pt-20">
            <div
                class="grid items-center gap-14 lg:grid-cols-[1.15fr_1fr] lg:gap-20"
            >
                <section>
                    <p class="journal-eyebrow">
                        Big milestones. Little victories. Your life.
                    </p>
                    <h1
                        class="mt-6 font-serif text-5xl leading-[1.08] tracking-tight sm:text-7xl"
                    >
                        Some days<br />deserve to be<br /><span
                            class="text-primary italic"
                            >remembered.</span
                        >
                    </h1>
                    <p
                        class="mt-7 max-w-md text-base leading-8 text-muted-foreground"
                    >
                        Life moves quickly. DDD gives your meaningful moments a
                        place to stay, from the everyday wins to the
                        achievements you’ll never forget.
                    </p>
                    <Button size="lg" class="mt-8" as-child
                        ><Link
                            :href="
                                $page.props.auth.user ? dashboard() : register()
                            "
                            >{{
                                $page.props.auth.user
                                    ? 'Open my journal'
                                    : 'Begin with a moment'
                            }}
                            <ArrowRight class="size-4" /></Link
                    ></Button>
                    <p
                        class="mt-4 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <LockKeyhole class="size-3.5" /> A personal space. Your
                        entries stay in your account.
                    </p>
                </section>
                <section
                    aria-label="An example journal entry"
                    class="relative rounded-3xl border bg-primary/5 p-5 sm:p-8"
                >
                    <p
                        class="mb-6 flex items-center gap-2 text-xs font-medium tracking-wider text-primary uppercase"
                    >
                        <BookOpen class="size-3.5" /> A page from a journal ·
                        example
                    </p>
                    <article
                        class="rounded-2xl border bg-card p-6 shadow-sm sm:p-8"
                    >
                        <p class="text-xs text-muted-foreground">
                            A day worth keeping
                        </p>
                        <span
                            class="mt-6 inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                            ><Check class="size-3" /> Accomplishment</span
                        >
                        <h2 class="mt-4 font-serif text-3xl leading-tight">
                            I finally did the thing<br />I kept putting off.
                        </h2>
                        <p class="mt-4 text-sm leading-7 text-muted-foreground">
                            It wasn’t perfect, and I was nervous. But I showed
                            up, took the first step, and felt a little more like
                            the person I want to be.
                        </p>
                        <div class="mt-6 border-l-2 border-primary/30 pl-4">
                            <p
                                class="text-[10px] font-semibold tracking-wider text-primary uppercase"
                            >
                                Why it mattered
                            </p>
                            <p class="mt-2 font-serif text-lg italic">
                                Small steps are still steps.
                            </p>
                        </div>
                    </article>
                    <p
                        class="mt-5 text-center font-serif text-sm text-primary/80 italic"
                    >
                        Not every moment needs to be extraordinary.
                    </p>
                </section>
            </div>
            <section
                aria-label="What belongs in your journal"
                class="mt-20 grid gap-8 border-t pt-10 sm:mt-28 sm:grid-cols-3 sm:gap-12"
            >
                <div
                    v-for="item in [
                        {
                            icon: Sparkles,
                            title: 'The moments',
                            text: 'A conversation, an adventure, a quiet realization. Keep the details that make a day yours.',
                        },
                        {
                            icon: Check,
                            title: 'The everyday wins',
                            text: 'Finished something difficult? Made a little progress? Give yourself a place to acknowledge it.',
                        },
                        {
                            icon: Trophy,
                            title: 'The milestones',
                            text: 'The goals you reached and the things you worked for. Look back and see how far you’ve come.',
                        },
                    ]"
                    :key="item.title"
                >
                    <component
                        :is="item.icon"
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />
                    <h2 class="mt-4 text-base font-semibold">
                        {{ item.title }}
                    </h2>
                    <p class="mt-2 text-sm leading-7 text-muted-foreground">
                        {{ item.text }}
                    </p>
                </div>
            </section>
        </main>
        <footer
            class="mx-auto max-w-6xl px-6 pb-8 text-xs text-muted-foreground sm:px-10"
        >
            ddd. &nbsp; Made for a life that’s yours.
        </footer>
    </div>
</template>
