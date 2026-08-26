<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    Search,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';

import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isMobile, state } = useSidebar();
const { isCurrentUrl } = useCurrentUrl();

const openMenus = ref<Record<string, boolean>>({});
const search = ref('');
const searchOpen = ref(false);

/*
|--------------------------------------------------------------------------
| Filter menu
|--------------------------------------------------------------------------
*/

const filteredItems = computed<NavItem[]>(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.items;
    }

    return props.items
        .map((item) => {
            const itemMatches = item.title
                .toLowerCase()
                .includes(term);

            if (!item.children?.length) {
                return itemMatches ? item : null;
            }

            const matchingChildren = item.children.filter((child) =>
                child.title.toLowerCase().includes(term),
            );

            if (itemMatches || matchingChildren.length) {
                return {
                    ...item,
                    children: itemMatches
                        ? item.children
                        : matchingChildren,
                };
            }

            return null;
        })
        .filter((item): item is NavItem => item !== null);
});

/*
|--------------------------------------------------------------------------
| Toggle submenu
|--------------------------------------------------------------------------
*/

const toggleMenu = (title: string) => {
    openMenus.value[title] = !openMenus.value[title];
};

/*
|--------------------------------------------------------------------------
| Active child
|--------------------------------------------------------------------------
*/

const hasActiveChild = (item: NavItem): boolean => {
    if (!item.children) {
        return false;
    }

    return item.children.some((child) => {
        if (child.href && isCurrentUrl(child.href)) {
            return true;
        }

        return hasActiveChild(child);
    });
};

/*
|--------------------------------------------------------------------------
| Menu open state
|--------------------------------------------------------------------------
*/

const isMenuOpen = (item: NavItem): boolean => {
    if (search.value.trim()) {
        return true;
    }

    return Boolean(
        openMenus.value[item.title] ||
        hasActiveChild(item),
    );
};

/*
|--------------------------------------------------------------------------
| Clear search
|--------------------------------------------------------------------------
*/

const clearSearch = () => {
    search.value = '';
};

/*
|--------------------------------------------------------------------------
| Close collapsed search
|--------------------------------------------------------------------------
*/

const closeSearch = () => {
    searchOpen.value = false;
    search.value = '';
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">

        <!-- ========================================================= -->
        <!-- SEARCH - EXPANDED -->
        <!-- ========================================================= -->

        <div
            v-if="state !== 'collapsed' || isMobile"
            class="mb-2"
        >
            <div class="relative">

                <Search
                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />

                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar ..."
                    class="h-9 w-full rounded-md border border-sidebar-border bg-sidebar-accent/30 pl-9 pr-8 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-sidebar-ring focus:ring-1 focus:ring-sidebar-ring"
                />

                <button
                    v-if="search"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-sm p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    @click="clearSearch"
                >
                    <X class="size-3.5" />
                </button>

            </div>
        </div>


        <!-- ========================================================= -->
        <!-- SEARCH - COLLAPSED -->
        <!-- ========================================================= -->

        <div
            v-else
            class="mb-2"
        >
            <DropdownMenu v-model:open="searchOpen">

                <DropdownMenuTrigger as-child>

                    <SidebarMenuButton
                        class="justify-center"
                    >
                        <Search class="size-4" />
                    </SidebarMenuButton>

                </DropdownMenuTrigger>

                <DropdownMenuContent
                    side="right"
                    align="start"
                    :side-offset="8"
                    class="w-72 rounded-lg p-3"
                >

                    <div class="mb-2 text-sm font-semibold">
                        Buscar
                    </div>

                    <div class="relative">

                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="text"
                            placeholder="Buscar..."
                            class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-8 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-1 focus:ring-ring"
                        />

                        <button
                            v-if="search"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-sm p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            @click="clearSearch"
                        >
                            <X class="size-3.5" />
                        </button>

                    </div>

                    <div class="mt-2 max-h-80 overflow-y-auto">

                        <template
                            v-for="item in filteredItems"
                            :key="item.title"
                        >

                            <!-- Normal result -->

                            <Link
                                v-if="!item.children?.length && item.href"
                                :href="item.href"
                                class="flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                                :class="{
                                    'bg-accent text-accent-foreground':
                                        isCurrentUrl(item.href),
                                }"
                                @click="closeSearch"
                            >

                                <component
                                    v-if="item.icon"
                                    :is="item.icon"
                                    class="size-4"
                                />

                                <span>
                                    {{ item.title }}
                                </span>

                            </Link>


                            <!-- Parent result -->

                            <div
                                v-else-if="item.children?.length"
                                class="mb-1"
                            >

                                <div
                                    class="px-2 py-1 text-xs font-semibold text-muted-foreground"
                                >
                                    {{ item.title }}
                                </div>

                                <Link
                                    v-for="child in item.children"
                                    :key="child.title"
                                    :href="child.href ?? '#'"
                                    class="flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                                    :class="{
                                        'bg-accent text-accent-foreground':
                                            child.href &&
                                            isCurrentUrl(child.href),
                                    }"
                                    @click="closeSearch"
                                >

                                    <component
                                        v-if="child.icon"
                                        :is="child.icon"
                                        class="size-4"
                                    />

                                    <span>
                                        {{ child.title }}
                                    </span>

                                </Link>

                            </div>

                        </template>


                        <!-- No results -->

                        <div
                            v-if="search && !filteredItems.length"
                            class="px-2 py-6 text-center text-sm text-muted-foreground"
                        >
                            Sin resultados.
                        </div>

                    </div>

                </DropdownMenuContent>

            </DropdownMenu>
        </div>


        <!-- ========================================================= -->
        <!-- MAIN MENU -->
        <!-- ========================================================= -->

        <SidebarMenu>

            <SidebarMenuItem
                v-for="item in filteredItems"
                :key="item.title"
            >

                <!-- ================================================= -->
                <!-- NORMAL MENU -->
                <!-- ================================================= -->

                <template v-if="!item.children?.length">

                    <SidebarMenuButton
                        v-if="item.href"
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >

                        <Link :href="item.href">

                            <component
                                v-if="item.icon"
                                :is="item.icon"
                            />

                            <span>
                                {{ item.title }}
                            </span>

                        </Link>

                    </SidebarMenuButton>

                </template>


                <!-- ================================================= -->
                <!-- MENU WITH SUBMENU -->
                <!-- ================================================= -->

                <template v-else>

                    <!-- COLLAPSED -->

                    <DropdownMenu
                        v-if="state === 'collapsed' && !isMobile"
                    >

                        <DropdownMenuTrigger as-child>

                            <SidebarMenuButton
                                :is-active="hasActiveChild(item)"
                            >

                                <component
                                    v-if="item.icon"
                                    :is="item.icon"
                                />

                            </SidebarMenuButton>

                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            class="min-w-56 rounded-lg"
                            side="right"
                            align="start"
                            :side-offset="8"
                        >

                            <div
                                class="px-2 py-1.5 text-sm font-semibold"
                            >
                                {{ item.title }}
                            </div>

                            <div class="my-1 h-px bg-border" />

                            <Link
                                v-for="child in item.children"
                                :key="child.title"
                                :href="child.href ?? '#'"
                                class="flex items-center gap-2 rounded-md px-2 py-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground"
                                :class="{
                                    'bg-accent text-accent-foreground':
                                        child.href &&
                                        isCurrentUrl(child.href),
                                }"
                            >

                                <component
                                    v-if="child.icon"
                                    :is="child.icon"
                                    class="size-4"
                                />

                                <span>
                                    {{ child.title }}
                                </span>

                            </Link>

                        </DropdownMenuContent>

                    </DropdownMenu>


                    <!-- EXPANDED -->

                    <template v-else>

                        <SidebarMenuButton
                            :is-active="hasActiveChild(item)"
                            @click="toggleMenu(item.title)"
                        >

                            <component
                                v-if="item.icon"
                                :is="item.icon"
                            />

                            <span>
                                {{ item.title }}
                            </span>

                            <ChevronDown
                                class="ml-auto size-4 transition-transform duration-200"
                                :class="{
                                    'rotate-180': isMenuOpen(item),
                                }"
                            />

                        </SidebarMenuButton>

                        <SidebarMenuSub
                            v-show="isMenuOpen(item)"
                        >

                            <SidebarMenuSubItem
                                v-for="child in item.children"
                                :key="child.title"
                            >

                                <SidebarMenuSubButton
                                    v-if="child.href"
                                    as-child
                                    :is-active="isCurrentUrl(child.href)"
                                >

                                    <Link :href="child.href">

                                        <component
                                            v-if="child.icon"
                                            :is="child.icon"
                                        />

                                        <span>
                                            {{ child.title }}
                                        </span>

                                    </Link>

                                </SidebarMenuSubButton>

                            </SidebarMenuSubItem>

                        </SidebarMenuSub>

                    </template>

                </template>

            </SidebarMenuItem>


            <!-- NO RESULTS -->

            <div
                v-if="search && !filteredItems.length"
                class="px-2 py-6 text-center text-sm text-muted-foreground"
            >
                Sin resultados.
            </div>

        </SidebarMenu>

    </SidebarGroup>
</template>