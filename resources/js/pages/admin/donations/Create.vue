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

import {
    ArrowLeft,
    Camera,
    CameraOff,
    Check,
    ChevronDown,
    CircleDollarSign,
    FileText,
    Info,
    QrCode,
    Save,
    Search,
    ShieldCheck,
    Sparkles,
    UserRound,
    Wallet,
    X,
} from 'lucide-vue-next';

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
                method.id === form.payment_method_id,
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
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <CircleDollarSign
                            class="h-6 w-6"
                        />
                    </div>

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
                </div>

                <Link
                    :href="
                        admin.donations
                            .index().url
                    "
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft
                        class="h-4 w-4"
                    />
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
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-6 flex flex-col gap-4 border-b border-sidebar-border/70 pb-6 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <UserRound
                                class="h-5 w-5"
                            />
                        </div>

                        <div>
                            <h2
                                class="text-lg font-semibold"
                            >
                                Usuario
                            </h2>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Identifica al visitante mediante su código QR.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <button
                            v-if="!scanning"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="
                                startQrScanner
                            "
                        >
                            <QrCode
                                class="h-4 w-4"
                            />
                            Escanear QR
                        </button>

                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="
                                stopQrScanner
                            "
                        >
                            <X
                                class="h-4 w-4"
                            />
                            Detener cámara
                        </button>
                    </div>
                </div>

                <!-- Lector QR -->
                <div
                    v-if="scanning"
                    class="flex justify-center"
                >
                    <div
                        class="relative h-[320px] w-[320px] max-w-full overflow-hidden rounded-xl border border-sidebar-border bg-black shadow-lg"
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
                                    class="absolute left-3 right-3 top-1/2 h-0.5 -translate-y-1/2 animate-pulse bg-primary opacity-90 shadow-[0_0_8px_currentColor]"
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
                                class="inline-flex items-center gap-2 rounded-full bg-black/60 px-3 py-1.5 text-xs text-white backdrop-blur-sm"
                            >
                                <QrCode
                                    class="h-3.5 w-3.5"
                                />
                                Coloca el QR dentro del recuadro
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Búsqueda manual -->
                <div
                    v-if="!identifiedUser"
                    class="mt-6 rounded-xl border border-dashed border-sidebar-border bg-muted/20 p-4"
                >
                    <div
                        class="mb-3 flex items-center gap-2"
                    >
                        <QrCode
                            class="h-4 w-4 text-muted-foreground"
                        />

                        <p
                            class="text-sm font-medium"
                        >
                            Identificación manual
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 sm:flex-row"
                    >
                        <div
                            class="relative flex-1"
                        >
                            <input
                                v-model="
                                    manualQrToken
                                "
                                type="text"
                                placeholder="Ingresa el código QR manualmente"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                @keyup.enter="
                                    searchManualQr
                                "
                            />

                            <span
                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"
                            >
                                QR
                            </span>
                        </div>

                        <button
                            type="button"
                            :disabled="
                                searchingUser ||
                                !manualQrToken.trim()
                            "
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="
                                searchManualQr
                            "
                        >
                            <Search
                                class="h-4 w-4"
                            />

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
                    class="mt-6 rounded-xl border border-green-500/30 bg-green-500/10 p-4"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-500/15 text-green-600 dark:text-green-400"
                            >
                                <Check
                                    class="h-5 w-5"
                                />
                            </div>

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
                                        class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border px-3 py-1 text-xs"
                                    >
                                        <ShieldCheck
                                            class="h-3.5 w-3.5"
                                        />

                                        {{
                                            identifiedUser
                                                .level
                                                ?.name ??
                                            'Sin nivel'
                                        }}
                                    </span>

                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border px-3 py-1 text-xs"
                                    >
                                        <CircleDollarSign
                                            class="h-3.5 w-3.5"
                                        />

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
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="
                                clearIdentifiedUser
                            "
                        >
                            <X
                                class="h-4 w-4"
                            />
                            Quitar usuario
                        </button>
                    </div>
                </div>

                <!-- Error -->
                <div
                    v-if="qrError"
                    class="mt-4 rounded-xl border border-red-500/30 bg-red-500/10 p-4"
                >
                    <div
                        class="flex items-start gap-3"
                    >
                        <Info
                            class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400"
                        />

                        <div>
                            <p
                                class="text-sm font-medium text-red-600 dark:text-red-400"
                            >
                                No se pudo identificar al usuario
                            </p>

                            <p
                                class="mt-1 text-sm text-red-600/80 dark:text-red-400/80"
                            >
                                {{ qrError }}
                            </p>
                        </div>
                    </div>
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
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-6 flex items-start gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Wallet
                            class="h-5 w-5"
                        />
                    </div>

                    <div>
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
                </div>

                <div
                    class="grid gap-6 md:grid-cols-2"
                >
                    <!-- Monto -->
                    <div class="space-y-2">
                        <label
                            for="amount"
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <CircleDollarSign
                                class="h-4 w-4 text-muted-foreground"
                            />
                            Monto
                        </label>

                        <div class="relative">
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
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <Wallet
                                class="h-4 w-4 text-muted-foreground"
                            />
                            Método de pago
                        </label>

                        <div class="relative">
                            <select
                                id="payment_method"
                                v-model="
                                    form.payment_method_id
                                "
                                class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                            <ChevronDown
                                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>

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
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <FileText
                            class="h-4 w-4 text-muted-foreground"
                        />
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
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-6 flex items-start gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Info
                            class="h-5 w-5"
                        />
                    </div>

                    <div>
                        <h2
                            class="text-lg font-semibold"
                        >
                            Resumen de la donación
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Revisa la información antes de registrar la donación.
                        </p>
                    </div>
                </div>

                <div
                    v-if="
                        selectedUser &&
                        selectedPaymentMethod &&
                        form.amount
                    "
                    class="space-y-4"
                >
                    <div
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <!-- Usuario -->
                        <div
                            class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <UserRound
                                    class="h-4 w-4"
                                />
                                Usuario
                            </div>

                            <p
                                class="font-medium"
                            >
                                {{
                                    selectedUser.name
                                }}
                            </p>
                        </div>

                        <!-- Monto -->
                        <div
                            class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <CircleDollarSign
                                    class="h-4 w-4"
                                />
                                Monto
                            </div>

                            <p
                                class="text-lg font-semibold"
                            >
                                {{
                                    formatCurrency(
                                        form.amount,
                                    )
                                }}
                            </p>
                        </div>

                        <!-- Método -->
                        <div
                            class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4"
                        >
                            <div
                                class="mb-2 flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <Wallet
                                    class="h-4 w-4"
                                />
                                Método de pago
                            </div>

                            <p
                                class="font-medium"
                            >
                                {{
                                    selectedPaymentMethod
                                        .name
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Referencia -->
                    <div
                        v-if="form.reference"
                        class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4"
                    >
                        <div
                            class="mb-2 flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <FileText
                                class="h-4 w-4"
                            />
                            Referencia
                        </div>

                        <p
                            class="break-words text-sm font-medium"
                        >
                            {{
                                form.reference
                            }}
                        </p>
                    </div>

                    <!-- Estado -->
                    <div
                        class="flex items-center gap-3 rounded-xl border border-green-500/30 bg-green-500/10 p-4"
                    >
                        <Check
                            class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400"
                        />

                        <div>
                            <p
                                class="text-sm font-medium text-green-700 dark:text-green-400"
                            >
                                La donación está lista para registrarse.
                            </p>

                            <p
                                class="mt-0.5 text-xs text-green-700/70 dark:text-green-400/70"
                            >
                                Verifica los datos antes de confirmar.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Vacío -->
                <div
                    v-else
                    class="rounded-xl border border-dashed border-sidebar-border bg-muted/20 p-8 text-center"
                >
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                    >
                        <CircleDollarSign
                            class="h-6 w-6"
                        />
                    </div>

                    <h3
                        class="mt-4 text-sm font-semibold"
                    >
                        Donación incompleta
                    </h3>

                    <p
                        class="mx-auto mt-1 max-w-md text-sm text-muted-foreground"
                    >
                        Identifica un usuario, ingresa el monto y selecciona un método de pago para continuar.
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
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <ArrowLeft
                            class="h-4 w-4"
                        />
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !canRegister
                        "
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Sparkles
                            v-if="form.processing"
                            class="h-4 w-4 animate-pulse"
                        />

                        <Save
                            v-else
                            class="h-4 w-4"
                        />

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