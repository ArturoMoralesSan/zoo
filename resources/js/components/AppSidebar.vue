<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    PawPrint,
    Ticket,
    Users,
    Settings,
} from '@lucide/vue';

import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },

    {
        title: 'Zoo Management',
        icon: PawPrint,

        children: [
            {
                title: 'Species',
                href: '#',
            },
            {
                title: 'Animals',
                href: '#',
            },
            {
                title: 'Habitats',
                href: '#',
            },
            {
                title: 'Cards',
                href: '#',
            },
        ],
    },

    {
        title: 'Operations',
        icon: Ticket,

        children: [
            {
                title: 'Tickets',
                href: '#',
            },
            {
                title: 'Donations',
                href: '#',
            },
            {
                title: 'Diplomas',
                href: '#',
            },
        ],
    },

    {
        title: 'Users',
        icon: Users,

        children: [
            {
                title: 'Users',
                href: '#',
            },
            {
                title: 'Roles',
                href: '#',
            },
            {
                title: 'Permissions',
                href: '#',
            },
        ],
    },

    {
        title: 'Configuration',
        icon: Settings,

        children: [
            {
                title: 'Settings',
                href: '#',
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },

    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">

        <!-- Header -->
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                    >
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!-- Main navigation -->
        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <!-- Footer -->
        <SidebarFooter>
            <NavFooter :items="footerNavItems" />

            <NavUser />
        </SidebarFooter>

    </Sidebar>

    <slot />
</template>