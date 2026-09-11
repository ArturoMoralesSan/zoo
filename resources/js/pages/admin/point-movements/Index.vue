<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

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
        <!-- Header -->
        <div
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <h1
                class="text-xl font-semibold"
            >
                Historial de puntos
            </h1>

            <p
                class="mt-1 text-sm text-muted-foreground"
            >
                Consulta los puntos ganados y descontados
                por los usuarios.
            </p>
        </div>

        <!-- Tabla -->
        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                v-if="movements.data.length"
                class="overflow-x-auto"
            >
                <table
                    class="w-full text-sm"
                >
                    <thead
                        class="border-b bg-muted/40"
                    >
                        <tr>
                            <th
                                class="px-6 py-4 text-left font-medium"
                            >
                                Fecha
                            </th>

                            <th
                                class="px-6 py-4 text-left font-medium"
                            >
                                Usuario
                            </th>

                            <th
                                class="px-6 py-4 text-left font-medium"
                            >
                                Acción
                            </th>

                            <th
                                class="px-6 py-4 text-left font-medium"
                            >
                                Puntos
                            </th>

                            <th
                                class="px-6 py-4 text-left font-medium"
                            >
                                Descripción
                            </th>

                            <th
                                class="px-6 py-4 text-left font-medium"
                            >
                                Referencia
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70"
                    >
                        <tr
                            v-for="movement in movements.data"
                            :key="movement.id"
                            class="hover:bg-muted/20"
                        >
                            <!-- Fecha -->
                            <td
                                class="whitespace-nowrap px-6 py-4"
                            >
                                {{
                                    formatDate(
                                        movement.created_at,
                                    )
                                }}
                            </td>

                            <!-- Usuario -->
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="font-medium"
                                >
                                    {{
                                        movement.user?.name ??
                                        'Usuario eliminado'
                                    }}
                                </div>

                                <div
                                    v-if="
                                        movement.user?.email
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        movement.user.email
                                    }}
                                </div>
                            </td>

                            <!-- Acción -->
                            <td
                                class="px-6 py-4"
                            >
                                <code
                                    class="rounded bg-muted px-2 py-1 text-xs"
                                >
                                    {{ movement.type }}
                                </code>
                            </td>

                            <!-- Puntos -->
                            <td
                                class="whitespace-nowrap px-6 py-4"
                            >
                                <span
                                    :class="
                                        movement.points > 0
                                            ? 'font-semibold text-green-600 dark:text-green-400'
                                            : 'font-semibold text-red-600 dark:text-red-400'
                                    "
                                >
                                    {{
                                        movement.points > 0
                                            ? '+'
                                            : ''
                                    }}{{
                                        movement.points
                                    }}
                                </span>
                            </td>

                            <!-- Descripción -->
                            <td
                                class="px-6 py-4"
                            >
                                {{
                                    movement.description ??
                                    '—'
                                }}
                            </td>

                            <!-- Referencia -->
                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    v-if="
                                        movement.reference_type &&
                                        movement.reference_id
                                    "
                                    class="text-xs"
                                >
                                    {{
                                        movement.reference_type
                                    }}
                                    #
                                    {{
                                        movement.reference_id
                                    }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
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
                class="p-10 text-center"
            >
                <p
                    class="font-medium"
                >
                    No hay movimientos de puntos.
                </p>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Los movimientos aparecerán aquí cuando
                    los usuarios ganen o gasten puntos.
                </p>
            </div>
        </div>
    </div>
</template>