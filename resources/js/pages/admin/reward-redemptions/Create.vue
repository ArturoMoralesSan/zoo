<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Html5Qrcode } from 'html5-qrcode';
import {
    ArrowLeft,
    CheckCircle2,
    Coins,
    Gift,
    QrCode,
    Search,
    ShieldCheck,
    User,
    UserRoundCheck,
    XCircle,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
} from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
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

const form = useForm({
    user_id: null as number | null,
    reward_id: null as number | null,
});

const identifiedUser = ref<User | null>(null);
const qrScanner = ref<Html5Qrcode | null>(null);
const scanning = ref(false);
const qrError = ref<string | null>(null);
const searchingUser = ref(false);
const manualQrToken = ref('');

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

const hasEnoughPoints = computed(() => {
    if (!selectedUser.value || !selectedReward.value) {
        return false;
    }

    return selectedUser.value.points >= selectedReward.value.points;
});

const canRedeem = computed(() => {
    if (!selectedUser.value || !selectedReward.value) {
        return false;
    }

    return hasEnoughPoints.value && selectedReward.value.stock > 0;
});

const missingPoints = computed(() => {
    if (!selectedUser.value || !selectedReward.value) {
        return 0;
    }

    return Math.max(
        selectedReward.value.points - selectedUser.value.points,
        0,
    );
});

const formatPoints = (value: number): string => {
    return value.toLocaleString('es-MX');
};

const findUserByQr = async (token: string): Promise<void> => {
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
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    qr_token: cleanToken,
                }),
            },
        );

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ?? 'No se pudo identificar al usuario.',
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
                : 'No se pudo identificar al usuario.';
    } finally {
        searchingUser.value = false;
    }
};

const startQrScanner = async (): Promise<void> => {
    if (scanning.value) {
        return;
    }

    qrError.value = null;

    try {
        scanning.value = true;

        await nextTick();

        const readerElement = document.getElementById('user-qr-reader');

        if (!readerElement) {
            throw new Error(
                'No se encontró el elemento #user-qr-reader.',
            );
        }

        qrScanner.value = new Html5Qrcode('user-qr-reader');

        await qrScanner.value.start(
            {
                facingMode: 'environment',
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
                await findUserByQr(decodedText);
            },
            () => {},
        );
    } catch (error) {
        console.error('Error iniciando lector QR:', error);

        scanning.value = false;
        qrScanner.value = null;

        if (error instanceof Error) {
            qrError.value = `Error de cámara: ${error.name} - ${error.message}`;
        } else {
            qrError.value = 'No fue posible iniciar el lector QR.';
        }
    }
};

const stopQrScanner = async (): Promise<void> => {
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
        console.error('Error deteniendo lector QR:', error);
    }

    qrScanner.value = null;
    scanning.value = false;
};

const clearIdentifiedUser = async (): Promise<void> => {
    await stopQrScanner();

    identifiedUser.value = null;
    form.user_id = null;
    qrError.value = null;
    manualQrToken.value = '';
};

const searchManualQr = async (): Promise<void> => {
    await findUserByQr(manualQrToken.value);
};

onBeforeUnmount(async () => {
    await stopQrScanner();
});

const submit = async (): Promise<void> => {
    if (!canRedeem.value) {
        return;
    }

    const result = await Swal.fire({
        title: '¿Confirmar canje?',
        text: `Se registrará el canje de "${selectedReward.value?.name}". Los puntos del usuario no se descontarán.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, canjear',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    form.post(admin.rewardRedemptions.store().url);
};
</script>

<template>
    <Head title="Nuevo canje de recompensa" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo canje de recompensa"
            description="Registra el canje de una recompensa por parte de un visitante."
            section="Recompensas"
            :icon="Gift"
            :action-icon="ArrowLeft"
            action-label="Regresar"
            :action-href="admin.rewardRedemptions.index().url"
        />

        <form
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <!-- Usuario -->
            <AdminCard
                title="Usuario"
                description="Escanea el código QR del visitante para identificar su cuenta."
                :icon="User"
            >
                <template #default>
                    <div class="flex flex-col gap-5">
                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminButton
                                v-if="!scanning"
                                type="button"
                                variant="secondary"
                                :icon="QrCode"
                                @click="startQrScanner"
                            >
                                Escanear QR
                            </AdminButton>

                            <AdminButton
                                v-else
                                type="button"
                                variant="secondary"
                                :icon="XCircle"
                                @click="stopQrScanner"
                            >
                                Detener cámara
                            </AdminButton>
                        </div>

                        <!-- Lector QR -->
                        <div
                            v-if="scanning"
                            class="flex justify-center"
                        >
                            <div
                                class="relative h-[320px] w-[320px] max-w-full overflow-hidden rounded-2xl border border-[#DCE7DF] bg-black"
                            >
                                <div
                                    id="user-qr-reader"
                                    class="absolute inset-0"
                                ></div>

                                <div
                                    class="pointer-events-none absolute inset-0 z-20 grid grid-cols-[1fr_230px_1fr] grid-rows-[1fr_230px_1fr]"
                                >
                                    <div class="col-span-3 bg-black/55"></div>
                                    <div class="bg-black/55"></div>

                                    <div class="relative">
                                        <span
                                            class="absolute left-0 top-0 h-9 w-9 rounded-tl-xl border-l-4 border-t-4 border-[#187744]"
                                        ></span>

                                        <span
                                            class="absolute right-0 top-0 h-9 w-9 rounded-tr-xl border-r-4 border-t-4 border-[#187744]"
                                        ></span>

                                        <span
                                            class="absolute bottom-0 left-0 h-9 w-9 rounded-bl-xl border-b-4 border-l-4 border-[#187744]"
                                        ></span>

                                        <span
                                            class="absolute bottom-0 right-0 h-9 w-9 rounded-br-xl border-b-4 border-r-4 border-[#187744]"
                                        ></span>

                                        <span
                                            class="absolute left-3 right-3 top-1/2 h-0.5 -translate-y-1/2 animate-pulse bg-[#187744] opacity-90"
                                        ></span>
                                    </div>

                                    <div class="bg-black/55"></div>
                                    <div class="col-span-3 bg-black/55"></div>
                                </div>

                                <div
                                    class="pointer-events-none absolute bottom-3 left-0 right-0 z-30 text-center"
                                >
                                    <span
                                        class="rounded-full bg-black/60 px-3 py-1 text-xs text-white"
                                    >
                                        Coloca el QR dentro del recuadro
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Búsqueda manual -->
                        <div v-if="!identifiedUser">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="manual-qr" class="form-label">
                                        Código QR
                                    </label>

                                    <input
                                        id="manual-qr"
                                        v-model="manualQrToken"
                                        type="text"
                                        placeholder="Ingresa el código QR manualmente"
                                        class="form-input"
                                        @keyup.enter="searchManualQr"
                                    />
                                </div>

                                <div class="form-field justify-end">
                                    <span class="form-label">
                                        Buscar usuario
                                    </span>

                                    <AdminButton
                                        type="button"
                                        variant="secondary"
                                        :icon="Search"
                                        :loading="searchingUser"
                                        loading-text="Buscando..."
                                        :disabled="!manualQrToken.trim()"
                                        @click="searchManualQr"
                                    >
                                        Buscar
                                    </AdminButton>
                                </div>
                            </div>
                        </div>

                        <!-- Usuario identificado -->
                        <div
                            v-if="identifiedUser"
                            class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]"
                                >
                                    <UserRoundCheck class="h-5 w-5" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-[#187744]">
                                        Usuario identificado
                                    </p>

                                    <p class="mt-1 text-base font-semibold text-[#244334]">
                                        {{ identifiedUser.name }}
                                    </p>

                                    <p class="mt-1 break-words text-sm text-[#718477]">
                                        {{ identifiedUser.email }}
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span
                                            class="inline-flex items-center rounded-full border border-[#DCE7DF] bg-white px-3 py-1 text-xs text-[#435D4C]"
                                        >
                                            {{ identifiedUser.level?.name ?? 'Sin nivel' }}
                                        </span>

                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-white px-3 py-1 text-xs text-[#435D4C]"
                                        >
                                            <Coins class="h-3.5 w-3.5" />
                                            {{ formatPoints(identifiedUser.points) }}
                                            puntos
                                        </span>
                                    </div>
                                </div>

                                <CancelButton
                                    :href="admin.rewardRedemptions.index().url"
                                    :icon="XCircle"
                                    label="Quitar"
                                    @click.prevent="clearIdentifiedUser"
                                />
                            </div>
                        </div>

                        <!-- Error QR -->
                        <div
                            v-if="qrError"
                            class="rounded-xl border border-red-200 bg-red-50 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <XCircle
                                    class="mt-0.5 h-5 w-5 shrink-0 text-red-600"
                                />

                                <p class="text-sm font-medium text-red-600">
                                    {{ qrError }}
                                </p>
                            </div>
                        </div>

                        <p v-if="form.errors.user_id" class="form-error">
                            {{ form.errors.user_id }}
                        </p>
                    </div>
                </template>
            </AdminCard>

            <!-- Recompensa -->
            <AdminCard
                title="Recompensa"
                description="Selecciona la recompensa que deseas entregar."
                :icon="Gift"
            >
                <div class="flex flex-col gap-5">
                    <div class="form-grid">
                        <div class="form-field form-field-full">
                            <label for="reward" class="form-label">
                                Recompensa
                                <span class="form-required">*</span>
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="reward"
                                    v-model="form.reward_id"
                                    class="form-select"
                                    :class="{
                                        'form-select-error': form.errors.reward_id,
                                    }"
                                >
                                    <option :value="null">
                                        Selecciona una recompensa
                                    </option>

                                    <option
                                        v-for="reward in rewards"
                                        :key="reward.id"
                                        :value="reward.id"
                                    >
                                        {{ reward.name }} —
                                        {{ formatPoints(reward.points) }}
                                        puntos
                                    </option>
                                </select>
                            </div>

                            <p
                                v-if="form.errors.reward_id"
                                class="form-error"
                            >
                                {{ form.errors.reward_id }}
                            </p>
                        </div>
                    </div>

                    <!-- Detalles de recompensa -->
                    <div
                        v-if="selectedReward"
                        class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]"
                            >
                                <Gift class="h-5 w-5" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-base font-semibold text-[#244334]">
                                    {{ selectedReward.name }}
                                </p>

                                <p
                                    v-if="selectedReward.description"
                                    class="mt-1 text-sm leading-5 text-[#718477]"
                                >
                                    {{ selectedReward.description }}
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-white px-3 py-1 text-xs text-[#435D4C]"
                                    >
                                        <Coins class="h-3.5 w-3.5" />
                                        {{ formatPoints(selectedReward.points) }}
                                        puntos requeridos
                                    </span>

                                    <span
                                        class="inline-flex items-center rounded-full border border-[#DCE7DF] bg-white px-3 py-1 text-xs text-[#435D4C]"
                                    >
                                        Stock: {{ formatPoints(selectedReward.stock) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </AdminCard>

            <!-- Resumen del canje -->
            <AdminCard
                title="Resumen del canje"
                description="Verifica la información antes de registrar el canje."
                :icon="ShieldCheck"
            >
                <div
                    v-if="selectedUser && selectedReward"
                    class="flex flex-col gap-4"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                            <p class="text-xs text-[#718477]">Usuario</p>

                            <p class="mt-1 break-words text-sm font-semibold text-[#244334]">
                                {{ selectedUser.name }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                            <p class="text-xs text-[#718477]">
                                Puntos disponibles
                            </p>

                            <p class="mt-1 text-lg font-semibold text-[#244334]">
                                {{ formatPoints(selectedUser.points) }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                            <p class="text-xs text-[#718477]">
                                Puntos requeridos
                            </p>

                            <p class="mt-1 text-lg font-semibold text-[#244334]">
                                {{ formatPoints(selectedReward.points) }}
                            </p>
                        </div>
                    </div>

                    <!-- Puntos insuficientes -->
                    <div
                        v-if="!hasEnoughPoints"
                        class="rounded-xl border border-red-200 bg-red-50 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />

                            <p class="text-sm font-medium text-red-600">
                                El usuario no tiene suficientes puntos. Le
                                faltan {{ formatPoints(missingPoints) }}
                                puntos.
                            </p>
                        </div>
                    </div>

                    <!-- Sin stock -->
                    <div
                        v-else-if="selectedReward.stock <= 0"
                        class="rounded-xl border border-red-200 bg-red-50 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />

                            <p class="text-sm font-medium text-red-600">
                                Esta recompensa está agotada.
                            </p>
                        </div>
                    </div>

                    <!-- Canje disponible -->
                    <div v-else class="form-info">
                        <div class="form-info-icon">
                            <CheckCircle2 class="h-5 w-5" />
                        </div>

                        <div>
                            <p class="form-info-text font-semibold">
                                El usuario puede canjear esta recompensa.
                            </p>

                            <p class="mt-1 text-sm leading-5 text-[#718477]">
                                Los puntos son acumulativos y no se
                                descontarán al realizar el canje.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-[#C8DCCF] bg-[#F7FAF8] p-6 text-center"
                >
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#EDF5EF] text-[#187744]"
                    >
                        <Gift class="h-6 w-6" />
                    </div>

                    <p class="mt-3 text-sm text-[#718477]">
                        Identifica un usuario y selecciona una recompensa para
                        continuar.
                    </p>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.rewardRedemptions.index().url"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Gift"
                    :loading="form.processing"
                    loading-text="Procesando..."
                    :disabled="!canRedeem"
                >
                    Canjear recompensa
                </AdminButton>
            </div>
        </form>
    </div>
</template>