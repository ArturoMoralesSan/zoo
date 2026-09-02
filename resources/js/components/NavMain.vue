<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
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
const page = usePage();

const openMenus = ref<Record<string, boolean>>({});
const search = ref('');
const searchOpen = ref(false);

/*
|--------------------------------------------------------------------------
| URL ACTIVA
|--------------------------------------------------------------------------
|
| Detecta tanto la ruta exacta como las rutas hijas.
|
| Ejemplo:
|
| /admin/users
| /admin/users/create
| /admin/users/1/edit
|
| Todas se consideran parte de Users.
|
|--------------------------------------------------------------------------
*/

const isActiveUrl = (
    href: string | null | undefined,
): boolean => {
    if (!href || href === '#') {
        return false;
    }

    const currentPath = page.url.split('?')[0];

    try {
        const menuPath = new URL(
            href,
            window.location.origin,
        ).pathname;

        return (
            currentPath === menuPath ||
            currentPath.startsWith(`${menuPath}/`)
        );
    } catch {
        return isCurrentUrl(href);
    }
};

/*
|--------------------------------------------------------------------------
| FILTER MENU
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

            /*
            |--------------------------------------------------------------------------
            | MENÚ SIN HIJOS
            |--------------------------------------------------------------------------
            */

            if (!item.children?.length) {
                return itemMatches ? item : null;
            }

            /*
            |--------------------------------------------------------------------------
            | MENÚ CON HIJOS
            |--------------------------------------------------------------------------
            */

            const matchingChildren = item.children.filter(
                (child) =>
                    child.title
                        .toLowerCase()
                        .includes(term),
            );

            if (
                itemMatches ||
                matchingChildren.length
            ) {
                return {
                    ...item,
                    children: itemMatches
                        ? item.children
                        : matchingChildren,
                };
            }

            return null;
        })
        .filter(
            (item): item is NavItem =>
                item !== null,
        );
});

/*
|--------------------------------------------------------------------------
| TOGGLE SUBMENU
|--------------------------------------------------------------------------
*/

const toggleMenu = (title: string) => {
    openMenus.value[title] =
        !openMenus.value[title];
};

/*
|--------------------------------------------------------------------------
| ACTIVE CHILD
|--------------------------------------------------------------------------
|
| Comprueba si alguno de los hijos pertenece a la
| ruta actual.
|
|--------------------------------------------------------------------------
*/

const hasActiveChild = (
    item: NavItem,
): boolean => {
    if (!item.children?.length) {
        return false;
    }

    return item.children.some((child) => {
        if (
            child.href &&
            isActiveUrl(child.href)
        ) {
            return true;
        }

        return hasActiveChild(child);
    });
};

/*
|--------------------------------------------------------------------------
| MENU OPEN STATE
|--------------------------------------------------------------------------
|
| Si un hijo está activo, el menú se abre automáticamente.
|
|--------------------------------------------------------------------------
*/

const isMenuOpen = (
    item: NavItem,
): boolean => {
    /*
    |--------------------------------------------------------------------------
    | Durante una búsqueda mostramos los resultados abiertos.
    |--------------------------------------------------------------------------
    */

    if (search.value.trim()) {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Si alguno de los hijos está activo, mantener abierto.
    |--------------------------------------------------------------------------
    */

    if (hasActiveChild(item)) {
        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Si el usuario lo abrió manualmente, mantenerlo abierto.
    |--------------------------------------------------------------------------
    */

    return Boolean(
        openMenus.value[item.title],
    );
};

/*
|--------------------------------------------------------------------------
| CLEAR SEARCH
|--------------------------------------------------------------------------
*/

const clearSearch = () => {
    search.value = '';
};

/*
|--------------------------------------------------------------------------
| CLOSE SEARCH
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
            v-if="
                state !== 'collapsed' ||
                isMobile
            "
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
            <DropdownMenu
                v-model:open="searchOpen"
            >

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

                    <div
                        class="mb-2 text-sm font-semibold"
                    >
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

                    <div
                        class="mt-2 max-h-80 overflow-y-auto"
                    >

                        <template
                            v-for="item in filteredItems"
                            :key="item.title"
                        >

                            <!-- ================================================= -->
                            <!-- NORMAL SEARCH RESULT -->
                            <!-- ================================================= -->

                            <Link
                                v-if="
                                    !item.children?.length &&
                                    item.href
                                "
                                :href="item.href"
                                class="flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                                :class="{
                                    'bg-accent text-accent-foreground':
                                        isActiveUrl(item.href),
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

                            <!-- ================================================= -->
                            <!-- PARENT SEARCH RESULT -->
                            <!-- ================================================= -->

                            <div
                                v-else-if="
                                    item.children?.length
                                "
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
                                    :href="
                                        child.href ?? '#'
                                    "
                                    class="flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                                    :class="{
                                        'bg-accent text-accent-foreground':
                                            child.href &&
                                            isActiveUrl(child.href),
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

                        <!-- ================================================= -->
                        <!-- NO SEARCH RESULTS -->
                        <!-- ================================================= -->

                        <div
                            v-if="
                                search &&
                                !filteredItems.length
                            "
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

                <template
                    v-if="!item.children?.length"
                >

                    <SidebarMenuButton
                        v-if="item.href"
                        as-child
                        :is-active="
                            isActiveUrl(item.href)
                        "
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

                    <!-- ================================================= -->
                    <!-- COLLAPSED SIDEBAR -->
                    <!-- ================================================= -->

                    <DropdownMenu
                        v-if="
                            state === 'collapsed' &&
                            !isMobile
                        "
                    >

                        <DropdownMenuTrigger as-child>

                            <SidebarMenuButton
                                :is-active="
                                    hasActiveChild(item)
                                "
                                :tooltip="item.title"
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

                            <div
                                class="my-1 h-px bg-border"
                            />

                            <Link
                                v-for="child in item.children"
                                :key="child.title"
                                :href="
                                    child.href ?? '#'
                                "
                                class="flex items-center gap-2 rounded-md px-2 py-2 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground"
                                :class="{
                                    'bg-accent text-accent-foreground':
                                        child.href &&
                                        isActiveUrl(child.href),
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

                    <!-- ================================================= -->
                    <!-- EXPANDED SIDEBAR -->
                    <!-- ================================================= -->

                    <template v-else>

                        <SidebarMenuButton
                            :is-active="
                                hasActiveChild(item)
                            "
                            @click="
                                toggleMenu(item.title)
                            "
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
                                    'rotate-180':
                                        isMenuOpen(item),
                                }"
                            />

                        </SidebarMenuButton>

                        <SidebarMenuSub
                            v-show="
                                isMenuOpen(item)
                            "
                        >

                            <SidebarMenuSubItem
                                v-for="child in item.children"
                                :key="child.title"
                            >

                                <SidebarMenuSubButton
                                    v-if="child.href"
                                    as-child
                                    :is-active="
                                        isActiveUrl(
                                            child.href,
                                        )
                                    "
                                >

                                    <Link
                                        :href="child.href"
                                    >

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

            <!-- ===================================================== -->
            <!-- NO RESULTS -->
            <!-- ===================================================== -->

            <div
                v-if="
                    search &&
                    !filteredItems.length
                "
                class="px-2 py-6 text-center text-sm text-muted-foreground"
            >
                Sin resultados.
            </div>

        </SidebarMenu>

    </SidebarGroup>
</template>