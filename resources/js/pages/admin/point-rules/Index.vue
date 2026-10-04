<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import {
    Coins,
    Edit,
    Plus,
    Search,
    Trash2,
    ToggleLeft,
    ToggleRight,
} from 'lucide-vue-next';

import admin from '@/routes/admin';

interface PointRule {
    id: number;
    type: string;
    name: string;
    points: number;
    description: string | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RulesPagination {
    data: PointRule[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    rules: RulesPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = (): void => {
    router.get(
        admin.pointRules.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteRule = async (
    rule: PointRule,
): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar regla?',
        text: `Se eliminará la regla "${rule.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(
        admin.pointRules.destroy(rule.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    title: 'Eliminada',
                    text: 'La regla de puntos se eliminó correctamente.',
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudo eliminar la regla de puntos.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar',
                });
            },
        },
    );
};
</script>

<template>
    <Head title="Reglas de puntos" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Coins class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Reglas de puntos
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra las reglas que generan o descuentan
                            puntos.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.pointRules.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nueva regla
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between"
            >
                <form
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar regla..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>
                </form>

                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Coins class="h-4 w-4" />

                    {{ rules.total }}

                    {{
                        rules.total === 1
                            ? 'regla'
                            : 'reglas'
                    }}
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-6 py-4 font-semibold">
                                Regla
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Tipo
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Puntos
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th
                                class="px-6 py-4 text-right font-semibold"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="rule in rules.data"
                            :key="rule.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Regla -->
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary"
                                    >
                                        <Coins class="h-4 w-4" />
                                    </span>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ rule.name }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID: {{ rule.id }}
                                        </div>

                                        <div
                                            v-if="rule.description"
                                            class="mt-1 max-w-md text-xs text-muted-foreground"
                                        >
                                            {{ rule.description }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Tipo -->
                            <td class="px-6 py-4">
                                <code
                                    class="inline-flex rounded-full border border-sidebar-border bg-muted/30 px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ rule.type }}
                                </code>
                            </td>

                            <!-- Puntos -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="rule.points > 0"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-semibold text-green-600 dark:text-green-400"
                                >
                                    +{{ rule.points }}
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-semibold text-red-600 dark:text-red-400"
                                >
                                    {{ rule.points }}
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="rule.is_active"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    <ToggleRight class="h-3.5 w-3.5" />

                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border bg-muted/30 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    <ToggleLeft class="h-3.5 w-3.5" />

                                    Inactiva
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.pointRules.edit(
                                                rule.id,
                                            ).url
                                        "
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Edit class="h-4 w-4" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteRule(rule)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="rules.data.length === 0"
                        >
                            <td
                                colspan="5"
                                class="px-6 py-16 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                                    >
                                        <Coins class="h-6 w-6" />
                                    </div>

                                    <h3
                                        class="mt-4 text-sm font-semibold text-foreground"
                                    >
                                        No se encontraron reglas de puntos
                                    </h3>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        No hay reglas que coincidan con la
                                        búsqueda.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="rules.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in rules.links"
                    :key="index"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg border px-3 py-2 text-sm transition"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-sidebar-border hover:bg-accent'
                        "
                        v-html="link.label"
                    />

                    <span
                        v-else
                        class="rounded-lg border border-sidebar-border px-3 py-2 text-sm opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>