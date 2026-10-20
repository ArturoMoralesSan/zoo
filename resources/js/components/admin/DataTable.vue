
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Search, FilterX } from 'lucide-vue-next';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationData {
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

withDefaults(
    defineProps<{
        pagination?: PaginationData;
        isEmpty?: boolean;
        emptyColspan?: number;
        search?: string;
        searchPlaceholder?: string;
        showSearch?: boolean;
        hasFilters?: boolean;
        showClearFilters?: boolean;
    }>(),
    {
        isEmpty: false,
        emptyColspan: 1,
        search: '',
        searchPlaceholder: 'Buscar registros...',
        showSearch: true,
        hasFilters: false,
        showClearFilters: true,
    },
);

const emit = defineEmits<{
    'update:search': [value: string];
    search: [];
    'clear-filters': [];
}>();
</script>

<template>
    <section class="overflow-hidden rounded-xl border border-[#dce7df] bg-white">
        <!-- Búsqueda y filtros -->
        <div
            v-if="showSearch || $slots.filters"
            class="border-b border-[#dce7df] p-4 sm:p-5"
        >
            <form
                class="grid grid-cols-1 items-center gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(200px,1fr)_minmax(160px,auto)_auto]"
                @submit.prevent="emit('search')"
            >
                <!-- Búsqueda -->
                <div v-if="showSearch" class="relative min-w-0">
                    <Search
                        :size="17"
                        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#718477]"
                    />

                    <input
                        :value="search"
                        type="search"
                        :placeholder="searchPlaceholder"
                        class="h-[42px] w-full rounded-lg border border-[#bfcfc6] bg-white pl-10 pr-3 text-sm text-[#244334] outline-none transition placeholder:text-[#84958a] focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10"
                        @input="
                            emit(
                                'update:search',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </div>

                <!-- Filtros personalizados -->
                <div
                    v-if="$slots.filters"
                    class="flex min-w-0 flex-wrap items-center gap-2"
                >
                    <slot name="filters" />
                </div>

                <!-- Buscar -->
                <button
                    v-if="showSearch"
                    type="submit"
                    class="inline-flex h-[42px] w-full shrink-0 items-center justify-center gap-2 rounded-lg border border-[#187744] bg-white px-5 text-sm font-semibold text-[#244334] transition hover:bg-[#e4f2e8] focus:outline-none focus:ring-2 focus:ring-[#187744]/15 sm:col-span-2 lg:col-span-1 lg:w-auto"
                >
                    <Search :size="16" />
                    Buscar
                </button>
            </form>

            <!-- Filtros activos: solo si hay filtros aplicados -->
            <div
                v-if="hasFilters && showClearFilters"
                class="mt-4 flex flex-wrap items-center gap-2"
            >
                <span class="text-xs font-medium text-[#718477]">
                    Filtros activos:
                </span>

                <slot name="active-filters" />

                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-[#187744] transition hover:bg-[#e4f2e8]"
                    @click="emit('clear-filters')"
                >
                    <FilterX :size="14" />
                    Limpiar filtros
                </button>
            </div>
        </div>

        <!-- Tabla -->
        <div class="w-full overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead
                    v-if="$slots['table-head']"
                    class="bg-[#edf4ef] text-xs font-semibold uppercase tracking-wide text-[#435d4c]"
                >
                    <slot name="table-head" />
                </thead>

                <tbody class="divide-y divide-[#e8eee9]">
                    <!-- Filas -->
                    <slot name="table-body" />

                    <!-- Estado vacío -->
                    <tr v-if="isEmpty">
                        <td
                            :colspan="emptyColspan"
                            class="px-6 py-14 text-center"
                        >
                            <slot name="empty">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    <div
                                        class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#f2f7f4] text-[#718477]"
                                    >
                                        <Search :size="22" />
                                    </div>

                                    <p class="text-sm font-semibold text-[#244334]">
                                        No se encontraron registros
                                    </p>

                                    <p class="mt-1 text-sm text-[#718477]">
                                        Intenta cambiar la búsqueda o los filtros.
                                    </p>
                                </div>
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Resumen y paginación -->
        <div
            v-if="pagination"
            class="flex flex-col gap-4 border-t border-[#dce7df] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"
        >
            <p class="text-sm text-[#718477]">
                <template v-if="pagination.total > 0">
                    Mostrando
                    <span class="font-semibold text-[#244334]">
                        {{ pagination.from ?? 0 }}
                    </span>
                    a
                    <span class="font-semibold text-[#244334]">
                        {{ pagination.to ?? 0 }}
                    </span>
                    de
                    <span class="font-semibold text-[#244334]">
                        {{ pagination.total }}
                    </span>
                    registros
                </template>

                <template v-else>
                    No hay registros para mostrar
                </template>
            </p>

            <!-- Ocultar navegación si solo existe una página -->
            <nav
                v-if="pagination.last_page > 1"
                class="flex flex-wrap items-center gap-1"
                aria-label="Paginación"
            >
                <template
                    v-for="(link, index) in pagination.links"
                    :key="`${index}-${link.label}`"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        :aria-current="link.active ? 'page' : undefined"
                        class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm transition"
                        :class="
                            link.active
                                ? 'border-[#187744] bg-[#187744] font-semibold text-white'
                                : 'border-[#dce7df] bg-white text-[#435d4c] hover:border-[#bfcfc6] hover:bg-[#f2f7f4]'
                        "
                    >
                        <span v-html="link.label" />
                    </Link>

                    <span
                        v-else
                        class="inline-flex min-h-9 min-w-9 cursor-not-allowed items-center justify-center rounded-lg border border-[#e8eee9] px-3 text-sm text-[#a0ada4] opacity-70"
                    >
                        <span v-html="link.label" />
                    </span>
                </template>
            </nav>
        </div>
    </section>
</template>
