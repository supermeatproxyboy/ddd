<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Plus } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { create } from '@/routes/entries';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'My journal',
        href: dashboard(),
        icon: BookOpen,
    },
    {
        title: 'Capture a moment',
        href: create(),
        icon: Plus,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <p
                class="px-3 py-4 font-serif text-sm leading-6 text-muted-foreground italic group-data-[collapsible=icon]:hidden"
            >
                A little space<br />for what matters.
            </p>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
