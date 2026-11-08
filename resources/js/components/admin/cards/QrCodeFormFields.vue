<script setup lang="ts">
import { CalendarClock, Infinity as InfinityIcon, QrCode } from 'lucide-vue-next';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import type { QrExpiryOption } from '@/lib/cardQrCode';

defineProps<{
    errors: Partial<
        Record<'label' | 'is_active' | 'expires' | 'expires_at', string>
    >;
}>();

const label = defineModel<string>('label', { required: true });
const isActive = defineModel<boolean>('isActive', { required: true });
const expires = defineModel<QrExpiryOption>('expires', { required: true });
const expiresAt = defineModel<string>('expiresAt', { required: true });

const expiryOptions = [
    {
        value: 'never' as const,
        title: 'Sin vencimiento (Default)',
        description: 'El QR funciona hasta que lo desactives o elimines',
        icon: InfinityIcon,
    },
    {
        value: 'date' as const,
        title: 'Fecha de vencimiento',
        description: 'El QR deja de funcionar en la fecha indicada',
        icon: CalendarClock,
    },
];
</script>

<template>
    <AdminCard
        title="Configuración del código QR"
        description="El QR inicia un duelo por esta tarjeta al escanearlo desde la app."
        :icon="QrCode"
    >
        <div class="form-grid">
            <!-- Etiqueta -->
            <div class="form-field form-field-full">
                <label for="label" class="form-label">Etiqueta</label>

                <input
                    id="label"
                    v-model="label"
                    type="text"
                    maxlength="255"
                    placeholder="Ej. Recinto del jaguar – entrada"
                    class="form-input"
                    :class="{ 'form-input-error': errors.label }"
                />

                <p v-if="errors.label" class="form-error">
                    {{ errors.label }}
                </p>

                <p v-else class="form-help">
                    Opcional. Te ayuda a identificar dónde se colocó este QR.
                </p>
            </div>

            <!-- Vencimiento -->
            <div class="form-field form-field-full">
                <label class="form-label">
                    Vigencia
                    <span class="text-red-500">*</span>
                </label>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label
                        v-for="option in expiryOptions"
                        :key="option.value"
                        class="flex flex-1 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition"
                        :class="
                            expires === option.value
                                ? 'border-[#187744] bg-[#F2F7F4] text-[#187744]'
                                : 'border-[#C8DCCF] bg-white text-[#435D4C]'
                        "
                    >
                        <input
                            v-model="expires"
                            type="radio"
                            :value="option.value"
                            class="accent-[#187744]"
                        />

                        <component :is="option.icon" :size="18" class="shrink-0" />

                        <div>
                            <p class="text-sm font-semibold">{{ option.title }}</p>
                            <p class="text-xs text-[#718477]">{{ option.description }}</p>
                        </div>
                    </label>
                </div>

                <p v-if="errors.expires" class="form-error">
                    {{ errors.expires }}
                </p>
            </div>

            <div
                v-if="expires === 'date'"
                class="form-field form-field-full"
            >
                <label for="expires_at" class="form-label">
                    Fecha y hora de vencimiento
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="expires_at"
                    v-model="expiresAt"
                    type="datetime-local"
                    required
                    class="form-input max-w-sm"
                    :class="{ 'form-input-error': errors.expires_at }"
                />

                <p v-if="errors.expires_at" class="form-error">
                    {{ errors.expires_at }}
                </p>
            </div>

            <!-- Estado -->
            <div class="form-field form-field-full">
                <ActiveSwitch
                    v-model="isActive"
                    label="QR activo"
                    description="Solo los QR activos y vigentes permiten iniciar un duelo."
                    :error="errors.is_active"
                />
            </div>
        </div>
    </AdminCard>
</template>
