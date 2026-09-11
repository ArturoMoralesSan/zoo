<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
} from 'vue';

import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';

import { Html5Qrcode } from 'html5-qrcode';

import Swal from 'sweetalert2';

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

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

const props = defineProps<{
    users: User[];
    paymentMethods: PaymentMethod[];
}>();

/*
|--------------------------------------------------------------------------
| Formulario
|--------------------------------------------------------------------------
*/

const form = useForm({
    user_id: null as number | null,
    payment_method_id: null as number | null,
    amount: null as number | null,
    reference: '',
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

const selectedPaymentMethod = computed(() => {
    if (!form.payment_method_id) {
        return null;
    }

    return (
        props.paymentMethods.find(
            (method) =>
                method.id ===
                form.payment_method_id,
        ) ?? null
    );
});

/*
|--------------------------------------------------------------------------
| Validación
|--------------------------------------------------------------------------
*/

const canRegister = computed(() => {
    return (
        !!selectedUser.value &&
        !!form.payment_method_id &&
        !!form.amount &&
        Number(form.amount) > 0
    );
});

/*
|--------------------------------------------------------------------------
| Formato
|--------------------------------------------------------------------------
*/

const formatCurrency = (
    value: number | null,
): string => {
    if (value === null) {
        return '$0.00';
    }

    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value);
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
            '/admin/donations/user-by-qr',
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
    if (!canRegister.value) {
        return;
    }

    const result =
        await Swal.fire({
            title: '¿Confirmar donación?',
            text: `Se registrará una donación de ${formatCurrency(Number(form.amount))} para ${selectedUser.value?.name}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText:
                'Sí, registrar',
            cancelButtonText:
                'Cancelar',
            reverseButtons: true,
        });

    if (!result.isConfirmed) {
        return;
    }

    form.post(
        admin.donations
            .store().url,
    );
};
</script>

<template>
    <Head title="Registrar donación" />

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
                        Registrar donación
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Registra una donación realizada por un visitante.
                    </p>
                </div>

                <Link
                    :href="
                        admin.donations
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
                            Escanea el código QR del visitante para identificar su cuenta.
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
                                        identifiedUser
                                            .points
                                            .toLocaleString(
                                                'es-MX',
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

            <!-- Donación -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Donación
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Ingresa el monto y selecciona el método de pago.
                    </p>
                </div>

                <div
                    class="grid gap-6 md:grid-cols-2"
                >
                    <!-- Monto -->
                    <div class="space-y-2">
                        <label
                            for="amount"
                            class="text-sm font-medium"
                        >
                            Monto
                        </label>

                        <div
                            class="relative"
                        >
                            <span
                                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground"
                            >
                                $
                            </span>

                            <input
                                id="amount"
                                v-model.number="
                                    form.amount
                                "
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-8 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>

                        <p
                            v-if="form.amount"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                formatCurrency(
                                    form.amount,
                                )
                            }}
                        </p>

                        <p
                            v-if="
                                form.errors.amount
                            "
                            class="text-sm text-red-500"
                        >
                            {{
                                form.errors.amount
                            }}
                        </p>
                    </div>

                    <!-- Método de pago -->
                    <div class="space-y-2">
                        <label
                            for="payment_method"
                            class="text-sm font-medium"
                        >
                            Método de pago
                        </label>

                        <select
                            id="payment_method"
                            v-model="
                                form.payment_method_id
                            "
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            <option
                                :value="null"
                            >
                                Selecciona un método de pago
                            </option>

                            <option
                                v-for="method in paymentMethods"
                                :key="method.id"
                                :value="method.id"
                            >
                                {{
                                    method.name
                                }}
                            </option>
                        </select>

                        <p
                            v-if="
                                form.errors
                                    .payment_method_id
                            "
                            class="text-sm text-red-500"
                        >
                            {{
                                form.errors
                                    .payment_method_id
                            }}
                        </p>
                    </div>
                </div>

                <!-- Referencia -->
                <div
                    class="mt-6 space-y-2"
                >
                    <label
                        for="reference"
                        class="text-sm font-medium"
                    >
                        Referencia
                        <span
                            class="font-normal text-muted-foreground"
                        >
                            (opcional)
                        </span>
                    </label>

                    <input
                        id="reference"
                        v-model="
                            form.reference
                        "
                        type="text"
                        maxlength="255"
                        placeholder="Referencia de la operación"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="
                            form.errors.reference
                        "
                        class="text-sm text-red-500"
                    >
                        {{
                            form.errors.reference
                        }}
                    </p>
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
                        Resumen de la donación
                    </h2>
                </div>

                <div
                    v-if="
                        selectedUser &&
                        selectedPaymentMethod &&
                        form.amount
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
                            Monto
                        </span>

                        <span
                            class="font-semibold"
                        >
                            {{
                                formatCurrency(
                                    form.amount,
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
                            Método de pago
                        </span>

                        <span
                            class="font-medium"
                        >
                            {{
                                selectedPaymentMethod
                                    .name
                            }}
                        </span>
                    </div>

                    <div
                        v-if="form.reference"
                        class="flex justify-between gap-4 text-sm"
                    >
                        <span
                            class="text-muted-foreground"
                        >
                            Referencia
                        </span>

                        <span
                            class="max-w-[60%] text-right font-medium break-words"
                        >
                            {{
                                form.reference
                            }}
                        </span>
                    </div>

                    <div
                        class="rounded-lg border border-green-500/30 bg-green-500/10 p-4"
                    >
                        <p
                            class="text-sm font-medium text-green-700 dark:text-green-400"
                        >
                            La donación está lista para registrarse.
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
                        Identifica un usuario, ingresa
                        el monto y selecciona un método
                        de pago para continuar.
                    </p>
                </div>

                <!-- Acciones -->
                <div
                    class="mt-6 flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="
                            admin.donations
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
                            !canRegister
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Procesando...'
                                : 'Registrar donación'
                        }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
