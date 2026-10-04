<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarClock,
    CircleMinus,
    CirclePlus,
    Coins,
    FileText,
    History,
    Link2,
    User,
} from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
}

interface PointMovement {
    id: number;
    user_id: number;
    points: number;
    type: string;
    description: string | null;
    reference_type: string | null;
    reference_id: number | null;
    created_at: string;
    user: User;
}

interface PaginatedMovements {
    data: PointMovement[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

defineProps<{
    movements: PaginatedMovements;
}>();

const formatDate = (
    date: string,
): string => {
    return new Date(date).toLocaleString(
        'es-MX',
        {
            dateStyle: 'short',
            timeStyle: 'short',
        },
    );
};
</script>

<template>
    <Head title="Historial de puntos" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex items-start gap-4"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <History class="h-5 w-5" />
                </div>

                <div>
                    <h1 class="text-2xl font-semibold">
                        Historial de puntos
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Consulta los puntos ganados y descontados
                        por los usuarios.
                    </p>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <div
                v-if="movements.data.length"
                class="overflow-x-auto"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-6 py-4 font-semibold">
                                Fecha
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Usuario
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Acción
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Puntos
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Descripción
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Referencia
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="movement in movements.data"
                            :key="movement.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Fecha -->
                            <td
                                class="whitespace-nowrap px-6 py-4"
                            >
                                <div class="flex items-center gap-2">
                                    <CalendarClock
                                        class="h-4 w-4 text-muted-foreground"
                                    />

                                    <span>
                                        {{
                                            formatDate(
                                                movement.created_at,
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>

                            <!-- Usuario -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary"
                                    >
                                        <User class="h-4 w-4" />
                                    </span>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{
                                                movement.user?.name ??
                                                'Usuario eliminado'
                                            }}
                                        </div>

                                        <div
                                            v-if="movement.user?.email"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                movement.user.email
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Acción -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border bg-muted/30 px-2.5 py-1 text-xs font-medium"
                                >
                                    <Coins
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                    />

                                    {{ movement.type }}
                                </span>
                            </td>

                            <!-- Puntos -->
                            <td
                                class="whitespace-nowrap px-6 py-4"
                            >
                                <span
                                    v-if="movement.points > 0"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-semibold text-green-600 dark:text-green-400"
                                >
                                    <CirclePlus class="h-3.5 w-3.5" />

                                    +{{ movement.points }}
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-semibold text-red-600 dark:text-red-400"
                                >
                                    <CircleMinus class="h-3.5 w-3.5" />

                                    {{ movement.points }}
                                </span>
                            </td>

                            <!-- Descripción -->
                            <td class="px-6 py-4">
                                <div
                                    v-if="movement.description"
                                    class="flex max-w-md items-start gap-2 text-sm text-muted-foreground"
                                >
                                    <FileText
                                        class="mt-0.5 h-4 w-4 shrink-0"
                                    />

                                    <span>
                                        {{ movement.description }}
                                    </span>
                                </div>

                                <span
                                    v-else
                                    class="text-sm italic text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Referencia -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="
                                        movement.reference_type &&
                                        movement.reference_id
                                    "
                                    class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    <Link2
                                        class="h-3.5 w-3.5 text-muted-foreground"
                                    />

                                    {{ movement.reference_type }}#{{
                                        movement.reference_id
                                    }}
                                </span>

                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Sin movimientos -->
            <div
                v-else
                class="flex min-h-[280px] flex-col items-center justify-center p-10 text-center"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                >
                    <Coins class="h-6 w-6" />
                </div>

                <h3
                    class="mt-4 text-sm font-semibold text-foreground"
                >
                    No hay movimientos de puntos
                </h3>

                <p
                    class="mt-1 max-w-md text-sm text-muted-foreground"
                >
                    Los movimientos aparecerán aquí cuando los usuarios
                    ganen o gasten puntos.
                </p>
            </div>
        </div>
    </div>
</template>