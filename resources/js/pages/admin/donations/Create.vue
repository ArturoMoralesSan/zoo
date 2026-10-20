<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Html5Qrcode } from 'html5-qrcode';
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
import Swal from 'sweetalert2';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';

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

const props = withDefaults(
    defineProps<{
        users?: User[];
        paymentMethods?: PaymentMethod[];
    }>(),
    {
        users: () => [],
        paymentMethods: () => [],
    },
);

const form = useForm({
    user_id: null as number | null,
    payment_method_id: null as number | null,
    amount: null as number | null,
    reference: '',
});

const identifiedUser = ref<User | null>(null);
const qrScanner = ref<Html5Qrcode | null>(null);
const scanning = ref(false);
const qrError = ref('');
const searchingUser = ref(false);
const manualQrToken = ref('');

const selectedUser = computed(() => {
    if (!form.user_id) return null;

    return (
        identifiedUser.value ??
        props.users.find((user) => user.id === form.user_id) ??
        null
    );
});

const selectedPaymentMethod = computed(() => {
    if (!form.payment_method_id) return null;

    return (
        props.paymentMethods.find(
            (method) => method.id === form.payment_method_id,
        ) ?? null
    );
});

const canRegister = computed(() => {
    return (
        !!selectedUser.value &&
        !!selectedPaymentMethod.value &&
        !!form.amount &&
        form.amount > 0
    );
});

const formatCurrency = (amount: number | null | undefined) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(amount ?? 0);
};

const findUserByQr = async (token: string) => {
    const cleanToken = token.trim();

    if (!cleanToken) return;

    searchingUser.value = true;
    qrError.value = '';

    try {
        const response = await fetch('/admin/donations/user-by-qr', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                qr_token: cleanToken,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ||
                    'No se encontró un usuario con ese código QR.',
            );
        }

        identifiedUser.value = data.user;
        form.user_id = data.user.id;
        manualQrToken.value = '';

        await stopQrScanner();
    } catch (error) {
        identifiedUser.value = null;
        form.user_id = null;

        qrError.value =
            error instanceof Error
                ? error.message
                : 'No fue posible identificar al usuario.';
    } finally {
        searchingUser.value = false;
    }
};

const startQrScanner = async () => {
    if (scanning.value) return;

    qrError.value = '';
    scanning.value = true;

    await nextTick();

    const reader = document.getElementById('user-qr-reader');

    if (!reader) {
        scanning.value = false;
        qrError.value = 'No se pudo inicializar el lector QR.';
        return;
    }

    try {
        qrScanner.value = new Html5Qrcode('user-qr-reader');

        await qrScanner.value.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: {
                    width: 230,
                    height: 230,
                },
                aspectRatio: 1,
            },
            async (decodedText) => {
                await findUserByQr(decodedText);
            },
            () => {},
        );
    } catch {
        qrError.value =
            'No se pudo activar la cámara. Verifica los permisos del navegador.';
        scanning.value = false;
        qrScanner.value = null;
    }
};

const stopQrScanner = async () => {
    if (qrScanner.value) {
        try {
            if (qrScanner.value.isScanning) {
                await qrScanner.value.stop();
            }

            await qrScanner.value.clear();
        } catch {
            // Evita que un error al detener la cámara interrumpa el formulario.
        }

        qrScanner.value = null;
    }

    scanning.value = false;
};

const clearIdentifiedUser = async () => {
    await stopQrScanner();

    identifiedUser.value = null;
    form.user_id = null;
    qrError.value = '';
    manualQrToken.value = '';
};

const searchManualQr = async () => {
    await findUserByQr(manualQrToken.value);
};

onBeforeUnmount(async () => {
    await stopQrScanner();
});

const submit = async () => {
    if (!canRegister.value || form.processing) return;

    const result = await Swal.fire({
        title: '¿Registrar donación?',
        text: `Se registrará una donación de ${formatCurrency(
            form.amount,
        )} para ${selectedUser.value?.name}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, registrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#187744',
        cancelButtonColor: '#718477',
        reverseButtons: true,
    });

    if (!result.isConfirmed) return;

    form.post(admin.donations.store().url);
};
</script>

<template>
    <Head title="Registrar donación" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Registrar donación"
            description="Registra una aportación y vincúlala con un usuario."
            section="Donaciones"
            :icon="CircleDollarSign"
            :action-icon="ArrowLeft"
            action-label="Volver a donaciones"
            :action-href="admin.donations.index().url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Identificación del usuario -->
            <AdminCard
                title="Identificación del usuario"
                description="Escanea el código QR o introduce el token del usuario."
                :icon="UserRound"
            >
                <div class="space-y-5">
                    <!-- Acciones del lector -->
                    <div class="flex flex-wrap gap-3">
                        <AdminButton
                            v-if="!scanning"
                            type="button"
                            :icon="Camera"
                            @click="startQrScanner"
                        >
                            Escanear código QR
                        </AdminButton>

                        <AdminButton
                            v-else
                            type="button"
                            variant="secondary"
                            :icon="CameraOff"
                            @click="stopQrScanner"
                        >
                            Cerrar cámara
                        </AdminButton>

                        <CancelButton
                            v-if="selectedUser"
                            :href="admin.donations.create().url"
                            :icon="X"
                        >
                            Cambiar usuario
                        </CancelButton>
                    </div>

                    <!-- Lector QR -->
                    <div
                        v-if="scanning"
                        class="overflow-hidden rounded-2xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                    >
                        <div class="mb-4 flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#EDF5EF] text-[#187744]"
                            >
                                <QrCode :size="20" />
                            </div>

                            <div>
                                <h3
                                    class="text-sm font-semibold text-[#244334]"
                                >
                                    Escáner de código QR
                                </h3>

                                <p class="text-xs text-[#718477]">
                                    Coloca el código dentro del área de lectura.
                                </p>
                            </div>
                        </div>

                        <div
                            class="mx-auto w-full max-w-sm overflow-hidden rounded-xl bg-white p-2"
                        >
                            <div
                                id="user-qr-reader"
                                class="min-h-[280px] w-full overflow-hidden rounded-lg"
                            />
                        </div>

                        <p class="mt-3 text-center text-xs text-[#718477]">
                            Permite el acceso a la cámara si tu navegador lo
                            solicita.
                        </p>
                    </div>

                    <!-- Búsqueda manual -->
                    <div class="form-field">
                        <label for="manual_qr_token" class="form-label">
                            Buscar por token QR
                        </label>

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <input
                                id="manual_qr_token"
                                v-model="manualQrToken"
                                type="text"
                                placeholder="Introduce el token del usuario"
                                class="form-input min-w-0 flex-1"
                                :disabled="searchingUser"
                                @keydown.enter.prevent="searchManualQr"
                            />

                            <AdminButton
                                type="button"
                                :icon="searchingUser ? Sparkles : Search"
                                :loading="searchingUser"
                                loading-text="Buscando..."
                                :disabled="
                                    searchingUser || !manualQrToken.trim()
                                "
                                @click="searchManualQr"
                            >
                                Buscar usuario
                            </AdminButton>
                        </div>

                        <p class="form-help">
                            También puedes localizar al usuario con su token QR.
                        </p>
                    </div>

                    <!-- Error del QR -->
                    <div
                        v-if="qrError"
                        class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4"
                    >
                        <Info
                            :size="19"
                            class="mt-0.5 shrink-0 text-red-600"
                        />

                        <p class="text-sm text-red-700">
                            {{ qrError }}
                        </p>
                    </div>

                    <p v-if="form.errors.user_id" class="form-error">
                        {{ form.errors.user_id }}
                    </p>

                    <!-- Usuario identificado -->
                    <div
                        v-if="selectedUser"
                        class="rounded-2xl border border-[#C8DCCF] bg-[#F7FAF8] p-4 sm:p-5"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#E3F1E7] text-[#187744]"
                            >
                                <Check :size="23" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div
                                    class="mb-3 flex flex-wrap items-center gap-2"
                                >
                                    <h3
                                        class="text-base font-semibold text-[#244334]"
                                    >
                                        {{ selectedUser.name }}
                                    </h3>

                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-[#E3F1E7] px-2.5 py-1 text-xs font-medium text-[#187744]"
                                    >
                                        <ShieldCheck :size="13" />
                                        Usuario identificado
                                    </span>
                                </div>

                                <p class="break-all text-sm text-[#718477]">
                                    {{ selectedUser.email }}
                                </p>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div
                                        class="rounded-xl border border-[#DCE7DF] bg-white p-3"
                                    >
                                        <p class="text-xs text-[#718477]">
                                            Nivel
                                        </p>

                                        <p
                                            class="mt-1 text-sm font-medium text-[#244334]"
                                        >
                                            {{
                                                selectedUser.level?.name ??
                                                'Sin nivel asignado'
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-xl border border-[#DCE7DF] bg-white p-3"
                                    >
                                        <p class="text-xs text-[#718477]">
                                            Puntos acumulados
                                        </p>

                                        <p
                                            class="mt-1 text-sm font-semibold text-[#187744]"
                                        >
                                            {{ selectedUser.points }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                title="Quitar usuario"
                                aria-label="Quitar usuario"
                                class="self-start rounded-lg p-2 text-[#718477] transition hover:bg-white hover:text-red-600"
                                @click="clearIdentifiedUser"
                            >
                                <X :size="18" />
                            </button>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                    >
                        <Info
                            :size="19"
                            class="mt-0.5 shrink-0 text-[#187744]"
                        />

                        <p class="text-sm leading-6 text-[#718477]">
                            Identifica al usuario para continuar con el registro
                            de la donación.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Datos de la donación -->
            <AdminCard
                title="Datos de la donación"
                description="Indica el importe, el método de pago y la referencia."
                :icon="Wallet"
            >
                <div class="form-grid">
                    <!-- Importe -->
                    <div class="form-field">
                        <label for="amount" class="form-label">
                            Importe de la donación
                            <span class="form-required">*</span>
                        </label>

                        <div class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-sm font-medium text-[#718477]"
                            >
                                $
                            </span>

                            <input
                                id="amount"
                                v-model.number="form.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                class="form-input pl-9"
                            />
                        </div>

                        <p class="form-help">
                            Introduce el importe en pesos mexicanos.
                        </p>

                        <p v-if="form.errors.amount" class="form-error">
                            {{ form.errors.amount }}
                        </p>
                    </div>

                    <!-- Método de pago -->
                    <div class="form-field">
                        <label for="payment_method_id" class="form-label">
                            Método de pago
                            <span class="form-required">*</span>
                        </label>

                        <div class="form-select-wrapper">
                            <select
                                id="payment_method_id"
                                v-model="form.payment_method_id"
                                class="form-select appearance-none"
                            >
                                <option :value="null">
                                    Selecciona un método
                                </option>

                                <option
                                    v-for="method in props.paymentMethods"
                                    :key="method.id"
                                    :value="method.id"
                                >
                                    {{ method.name }}
                                </option>
                            </select>

                            <ChevronDown
                                :size="16"
                                class="form-select-icon"
                            />
                        </div>

                        <p
                            v-if="form.errors.payment_method_id"
                            class="form-error"
                        >
                            {{ form.errors.payment_method_id }}
                        </p>
                    </div>

                    <!-- Referencia -->
                    <div class="form-field form-field-full">
                        <label for="reference" class="form-label">
                            Referencia
                        </label>

                        <input
                            id="reference"
                            v-model="form.reference"
                            type="text"
                            placeholder="Folio, número de operación o referencia"
                            class="form-input"
                        />

                        <p class="form-help">
                            Campo opcional para identificar la operación.
                        </p>

                        <p v-if="form.errors.reference" class="form-error">
                            {{ form.errors.reference }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Resumen -->
            <AdminCard
                title="Resumen de la donación"
                description="Revisa los datos antes de registrar la aportación."
                :icon="FileText"
            >
                <div
                    v-if="
                        selectedUser &&
                        selectedPaymentMethod &&
                        form.amount &&
                        form.amount > 0
                    "
                    class="overflow-hidden rounded-xl border border-[#DCE7DF] bg-[#F7FAF8]"
                >
                    <div
                        class="flex items-center justify-between gap-4 border-b border-[#DCE7DF] p-4"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#187744]"
                            >
                                <UserRound :size="19" />
                            </div>

                            <div class="min-w-0">
                                <p class="text-xs text-[#718477]">
                                    Usuario
                                </p>

                                <p
                                    class="break-words text-sm font-medium text-[#244334]"
                                >
                                    {{ selectedUser.name }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 border-b border-[#DCE7DF] p-4"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#187744]"
                            >
                                <Wallet :size="19" />
                            </div>

                            <div>
                                <p class="text-xs text-[#718477]">
                                    Método de pago
                                </p>

                                <p
                                    class="text-sm font-medium text-[#244334]"
                                >
                                    {{ selectedPaymentMethod.name }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 border-b border-[#DCE7DF] p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-[#187744]"
                            >
                                <FileText :size="19" />
                            </div>

                            <div>
                                <p class="text-xs text-[#718477]">
                                    Referencia
                                </p>

                                <p
                                    class="break-words text-sm font-medium text-[#244334]"
                                >
                                    {{
                                        form.reference.trim() ||
                                        'Sin referencia'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-sm font-medium text-[#435D4C]">
                            Total de la donación
                        </p>

                        <p
                            class="text-2xl font-semibold tracking-tight text-[#187744]"
                        >
                            {{ formatCurrency(form.amount) }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                >
                    <Info
                        :size="19"
                        class="mt-0.5 shrink-0 text-[#187744]"
                    />

                    <p class="text-sm leading-6 text-[#718477]">
                        Completa la identificación del usuario, el importe y el
                        método de pago para visualizar el resumen.
                    </p>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col gap-3 rounded-2xl border border-[#DCE7DF] bg-white p-4 sm:flex-row sm:items-center sm:justify-end sm:px-6"
            >
                <p class="form-help sm:mr-auto">
                    <span class="form-required">*</span>
                    Campos obligatorios.
                </p>

                <CancelButton
                    :href="admin.donations.index().url"
                    :icon="ArrowLeft"
                >
                    Cancelar
                </CancelButton>

                <AdminButton
                    type="submit"
                    :disabled="form.processing || !canRegister"
                    :loading="form.processing"
                    loading-text="Guardando donación..."
                    :icon="Save"
                >
                    Registrar donación
                </AdminButton>
            </div>
        </form>
    </div>
</template>