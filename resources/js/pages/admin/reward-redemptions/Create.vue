<script setup lang="ts">

import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';

import { Html5Qrcode } from 'html5-qrcode';

import Swal from 'sweetalert2';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
} from 'vue';

import admin from '@/routes/admin';

interface UserLevel {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    points: number;
    qr_token: string;
    level: UserLevel | null;
}

interface Reward {
    id: number;
    name: string;
    description: string | null;
    points: number;
    stock: number;
    image: string | null;
}

const props = defineProps<{
    users: User[];
    rewards: Reward[];
}>();

/*
|--------------------------------------------------------------------------
| Formulario
|--------------------------------------------------------------------------
*/

const form = useForm({
    user_id: null as number | null,
    reward_id: null as number | null,
});

/*
|--------------------------------------------------------------------------
| Usuario / QR
|--------------------------------------------------------------------------
*/

const identifiedUser = ref<User | null>(null);

const qrScanner = ref<Html5Qrcode | null>(null);

const scanning = ref(false);

const qrError = ref<string | null>(null);

const searchingUser = ref(false);

const manualQrToken = ref('');

/*
|--------------------------------------------------------------------------
| Selecciones
|--------------------------------------------------------------------------
*/

const selectedUser = computed(() => {
    if (!form.user_id) {
        return null;
    }

    return (
        identifiedUser.value ??
        props.users.find(
            (user) => user.id === form.user_id,
        ) ??
        null
    );
});

const selectedReward = computed(() => {
    if (!form.reward_id) {
        return null;
    }

    return (
        props.rewards.find(
            (reward) => reward.id === form.reward_id,
        ) ?? null
    );
});

/*
|--------------------------------------------------------------------------
| Validaciones del canje
|--------------------------------------------------------------------------
*/

const hasEnoughPoints = computed(() => {
    if (
        !selectedUser.value ||
        !selectedReward.value
    ) {
        return false;
    }

    return (
        selectedUser.value.points >=
        selectedReward.value.points
    );
});

const canRedeem = computed(() => {
    if (
        !selectedUser.value ||
        !selectedReward.value
    ) {
        return false;
    }

    return (
        hasEnoughPoints.value &&
        selectedReward.value.stock > 0
    );
});

const missingPoints = computed(() => {
    if (
        !selectedUser.value ||
        !selectedReward.value
    ) {
        return 0;
    }

    return Math.max(
        selectedReward.value.points -
            selectedUser.value.points,
        0,
    );
});

/*
|--------------------------------------------------------------------------
| Formato
|--------------------------------------------------------------------------
*/

const formatPoints = (
    value: number,
): string => {
    return value.toLocaleString('es-MX');
};

/*
|--------------------------------------------------------------------------
| Usuario / QR
|--------------------------------------------------------------------------
*/

const findUserByQr = async (
    token: string,
): Promise<void> => {
    const cleanToken = token.trim();

    if (!cleanToken) {
        return;
    }

    searchingUser.value = true;
    qrError.value = null;

    try {
        const response = await fetch(
            '/admin/reward-redemptions/user-by-qr',
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    Accept:
                        'application/json',

                    'X-CSRF-TOKEN':
                        document
                            .querySelector(
                                'meta[name="csrf-token"]',
                            )
                            ?.getAttribute(
                                'content',
                            ) ?? '',
                },

                body: JSON.stringify({
                    qr_token: cleanToken,
                }),
            },
        );

        const data =
            await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ??
                    'No se pudo identificar al usuario.',
            );
        }

        identifiedUser.value =
            data.user;

        form.user_id =
            data.user.id;

        manualQrToken.value = '';

        await stopQrScanner();
    } catch (error) {
        identifiedUser.value = null;

        form.user_id = null;

        qrError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo identificar al usuario.';
    } finally {
        searchingUser.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Iniciar lector QR
|--------------------------------------------------------------------------
*/

const startQrScanner =
    async (): Promise<void> => {
        if (scanning.value) {
            return;
        }

        qrError.value = null;

        try {
            scanning.value = true;

            await nextTick();

            const readerElement =
                document.getElementById(
                    'user-qr-reader',
                );

            if (!readerElement) {
                throw new Error(
                    'No se encontró el elemento #user-qr-reader.',
                );
            }

            qrScanner.value =
                new Html5Qrcode(
                    'user-qr-reader',
                );

            await qrScanner.value.start(
                {
                    facingMode:
                        'environment',
                },
                {
                    fps: 10,

                    qrbox: {
                        width: 230,
                        height: 230,
                    },

                    aspectRatio: 1,
                },
                async (decodedText) => {
                    await findUserByQr(
                        decodedText,
                    );
                },
                () => {},
            );
        } catch (error) {
            console.error(
                'Error iniciando lector QR:',
                error,
            );

            scanning.value = false;

            qrScanner.value = null;

            if (error instanceof Error) {
                qrError.value =
                    `Error de cámara: ${error.name} - ${error.message}`;
            } else {
                qrError.value =
                    'No fue posible iniciar el lector QR.';
            }
        }
    };

/*
|--------------------------------------------------------------------------
| Detener lector QR
|--------------------------------------------------------------------------
*/

const stopQrScanner =
    async (): Promise<void> => {
        if (!qrScanner.value) {
            scanning.value = false;

            return;
        }

        try {
            if (scanning.value) {
                await qrScanner.value.stop();
            }

            await qrScanner.value.clear();
        } catch (error) {
            console.error(
                'Error deteniendo lector QR:',
                error,
            );
        }

        qrScanner.value = null;

        scanning.value = false;
    };

/*
|--------------------------------------------------------------------------
| Quitar usuario
|--------------------------------------------------------------------------
*/

const clearIdentifiedUser =
    async (): Promise<void> => {
        await stopQrScanner();

        identifiedUser.value = null;

        form.user_id = null;

        qrError.value = null;

        manualQrToken.value = '';
    };

/*
|--------------------------------------------------------------------------
| Buscar QR manualmente
|--------------------------------------------------------------------------
*/

const searchManualQr =
    async (): Promise<void> => {
        await findUserByQr(
            manualQrToken.value,
        );
    };

/*
|--------------------------------------------------------------------------
| Limpiar cámara al salir
|--------------------------------------------------------------------------
*/

onBeforeUnmount(async () => {
    await stopQrScanner();
});

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

const submit = async (): Promise<void> => {
    if (!canRedeem.value) {
        return;
    }

    const result =
        await Swal.fire({
            title: '¿Confirmar canje?',
            text: `Se registrará el canje de "${selectedReward.value?.name}". Los puntos del usuario no se descontarán.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText:
                'Sí, canjear',
            cancelButtonText:
                'Cancelar',
            reverseButtons: true,
        });

    if (!result.isConfirmed) {
        return;
    }

    form.post(
        admin.rewardRedemptions
            .store().url,
    );
};
</script>

<template>
    <Head title="Nuevo canje de recompensa" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Header -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold"
                    >
                        Nuevo canje de recompensa
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Registra el canje de una
                        recompensa por parte de un
                        visitante.
                    </p>
                </div>

                <Link
                    :href="
                        admin.rewardRedemptions
                            .index().url
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <form
            class="space-y-4"
            @submit.prevent="submit"
        >
            <!-- Usuario -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h2
                            class="text-lg font-semibold"
                        >
                            Usuario
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Escanea el código QR del
                            visitante para identificar
                            su cuenta.
                        </p>
                    </div>

                    <button
                        v-if="!scanning"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                        @click="
                            startQrScanner
                        "
                    >
                        Escanear QR
                    </button>

                    <button
                        v-else
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                        @click="
                            stopQrScanner
                        "
                    >
                        Detener cámara
                    </button>
                </div>

                <!-- Lector QR -->
                <div
                    v-if="scanning"
                    class="mt-4 flex justify-center"
                >
                    <div
                        class="relative h-[320px] w-[320px] overflow-hidden rounded-2xl border border-sidebar-border bg-black shadow-lg"
                    >
                        <div
                            id="user-qr-reader"
                            class="absolute inset-0"
                        ></div>

                        <div
                            class="pointer-events-none absolute inset-0 z-20 grid grid-cols-[1fr_230px_1fr] grid-rows-[1fr_230px_1fr]"
                        >
                            <div
                                class="col-span-3 bg-black/55"
                            ></div>

                            <div
                                class="bg-black/55"
                            ></div>

                            <div
                                class="relative"
                            >
                                <span
                                    class="absolute left-0 top-0 h-9 w-9 rounded-tl-xl border-l-4 border-t-4 border-primary"
                                ></span>

                                <span
                                    class="absolute right-0 top-0 h-9 w-9 rounded-tr-xl border-r-4 border-t-4 border-primary"
                                ></span>

                                <span
                                    class="absolute bottom-0 left-0 h-9 w-9 rounded-bl-xl border-b-4 border-l-4 border-primary"
                                ></span>

                                <span
                                    class="absolute bottom-0 right-0 h-9 w-9 rounded-br-xl border-b-4 border-r-4 border-primary"
                                ></span>

                                <span
                                    class="absolute left-3 right-3 top-1/2 h-0.5 -translate-y-1/2 bg-primary opacity-90 shadow-[0_0_8px_currentColor] animate-pulse"
                                ></span>
                            </div>

                            <div
                                class="bg-black/55"
                            ></div>

                            <div
                                class="col-span-3 bg-black/55"
                            ></div>
                        </div>

                        <div
                            class="pointer-events-none absolute bottom-3 left-0 right-0 z-30 text-center"
                        >
                            <span
                                class="rounded-full bg-black/60 px-3 py-1 text-xs text-white backdrop-blur-sm"
                            >
                                Coloca el QR dentro
                                del recuadro
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Búsqueda manual -->
                <div
                    v-if="!identifiedUser"
                    class="mt-4"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row"
                    >
                        <input
                            v-model="
                                manualQrToken
                            "
                            type="text"
                            placeholder="También puedes ingresar el código QR manualmente"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            @keyup.enter="
                                searchManualQr
                            "
                        />

                        <button
                            type="button"
                            :disabled="
                                searchingUser ||
                                !manualQrToken.trim()
                            "
                            class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                            @click="
                                searchManualQr
                            "
                        >
                            {{
                                searchingUser
                                    ? 'Buscando...'
                                    : 'Buscar'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Usuario identificado -->
                <div
                    v-if="identifiedUser"
                    class="mt-4 rounded-lg border border-green-500/30 bg-green-500/10 p-4"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-sm font-medium text-green-700 dark:text-green-400"
                            >
                                Usuario identificado
                            </p>

                            <p
                                class="mt-1 text-lg font-semibold"
                            >
                                {{
                                    identifiedUser.name
                                }}
                            </p>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{
                                    identifiedUser.email
                                }}
                            </p>

                            <div
                                class="mt-2 flex flex-wrap gap-2"
                            >
                                <span
                                    class="rounded-full border border-sidebar-border px-3 py-1 text-xs"
                                >
                                    {{
                                        identifiedUser
                                            .level
                                            ?.name ??
                                        'Sin nivel'
                                    }}
                                </span>

                                <span
                                    class="rounded-full border border-sidebar-border px-3 py-1 text-xs"
                                >
                                    {{
                                        formatPoints(
                                            identifiedUser.points,
                                        )
                                    }}
                                    puntos
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                            @click="
                                clearIdentifiedUser
                            "
                        >
                            Quitar usuario
                        </button>
                    </div>
                </div>

                <!-- Error -->
                <div
                    v-if="qrError"
                    class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-4"
                >
                    <p
                        class="text-sm font-medium text-red-600 dark:text-red-400"
                    >
                        {{ qrError }}
                    </p>
                </div>

                <p
                    v-if="form.errors.user_id"
                    class="mt-3 text-sm text-red-500"
                >
                    {{ form.errors.user_id }}
                </p>
            </div>

            <!-- Recompensa -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Recompensa
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Selecciona la recompensa que
                        deseas entregar.
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        for="reward"
                        class="text-sm font-medium"
                    >
                        Recompensa
                    </label>

                    <select
                        id="reward"
                        v-model="
                            form.reward_id
                        "
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option
                            :value="null"
                        >
                            Selecciona una recompensa
                        </option>

                        <option
                            v-for="reward in rewards"
                            :key="reward.id"
                            :value="reward.id"
                        >
                            {{ reward.name }}
                            —
                            {{
                                formatPoints(
                                    reward.points,
                                )
                            }}
                            puntos
                        </option>
                    </select>

                    <p
                        v-if="
                            form.errors.reward_id
                        "
                        class="text-sm text-red-500"
                    >
                        {{
                            form.errors.reward_id
                        }}
                    </p>
                </div>

                <!-- Información recompensa -->
                <div
                    v-if="selectedReward"
                    class="mt-4 rounded-lg border border-sidebar-border p-4"
                >
                    <p
                        class="text-lg font-semibold"
                    >
                        {{
                            selectedReward.name
                        }}
                    </p>

                    <p
                        v-if="
                            selectedReward.description
                        "
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{
                            selectedReward.description
                        }}
                    </p>

                    <div
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <span
                            class="rounded-full border border-sidebar-border px-3 py-1 text-xs"
                        >
                            {{
                                formatPoints(
                                    selectedReward.points,
                                )
                            }}
                            puntos requeridos
                        </span>

                        <span
                            class="rounded-full border border-sidebar-border px-3 py-1 text-xs"
                        >
                            Stock:
                            {{
                                formatPoints(
                                    selectedReward.stock,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Resumen -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Resumen del canje
                    </h2>
                </div>

                <div
                    v-if="
                        selectedUser &&
                        selectedReward
                    "
                    class="space-y-3"
                >
                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-muted-foreground"
                        >
                            Usuario
                        </span>

                        <span
                            class="font-medium"
                        >
                            {{
                                selectedUser.name
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-muted-foreground"
                        >
                            Puntos disponibles
                        </span>

                        <span
                            class="font-medium"
                        >
                            {{
                                formatPoints(
                                    selectedUser.points,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-muted-foreground"
                        >
                            Puntos requeridos
                        </span>

                        <span
                            class="font-medium"
                        >
                            {{
                                formatPoints(
                                    selectedReward.points,
                                )
                            }}
                        </span>
                    </div>

                    <!-- Sin puntos suficientes -->
                    <div
                        v-if="
                            !hasEnoughPoints
                        "
                        class="rounded-lg border border-red-500/30 bg-red-500/10 p-4"
                    >
                        <p
                            class="text-sm font-medium text-red-600 dark:text-red-400"
                        >
                            El usuario no tiene
                            suficientes puntos.

                            Le faltan
                            {{
                                formatPoints(
                                    missingPoints,
                                )
                            }}
                            puntos.
                        </p>
                    </div>

                    <!-- Sin stock -->
                    <div
                        v-else-if="
                            selectedReward.stock <=
                            0
                        "
                        class="rounded-lg border border-red-500/30 bg-red-500/10 p-4"
                    >
                        <p
                            class="text-sm font-medium text-red-600 dark:text-red-400"
                        >
                            Esta recompensa está
                            agotada.
                        </p>
                    </div>

                    <!-- Puede canjear -->
                    <div
                        v-else
                        class="rounded-lg border border-green-500/30 bg-green-500/10 p-4"
                    >
                        <p
                            class="text-sm font-medium text-green-700 dark:text-green-400"
                        >
                            El usuario puede canjear
                            esta recompensa.
                        </p>

                        <p
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Los puntos son acumulativos
                            y no se descontarán al
                            realizar el canje.
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                >
                    <p
                        class="text-sm text-muted-foreground"
                    >
                        Identifica un usuario y
                        selecciona una recompensa para
                        continuar.
                    </p>
                </div>

                <!-- Acciones -->
                <div
                    class="mt-6 flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="
                            admin.rewardRedemptions
                                .index().url
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !canRedeem
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Procesando...'
                                : 'Canjear recompensa'
                        }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>