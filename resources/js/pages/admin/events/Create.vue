<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronDown,
    Clock,
    FileImage,
    Info,
    MapPin,
    Save,
    Star,
    Users,
} from 'lucide-vue-next';

import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import admin from '@/routes/admin';

defineOptions({
    breadcrumbs: [
        { title: 'Panel', href: admin.dashboard().url },
        { title: 'Eventos', href: admin.events.index().url },
        { title: 'Nuevo evento', href: '' },
    ],
});

interface Zone {
    id: number;
    name: string;
}

const props = defineProps<{
    zones: Zone[];
}>();

const form = useForm({
    name: '',
    description: '',
    type: '',
    start_at: '',
    end_at: '',
    zoo_zone_id: '',
    image: null as File | null,
    capacity: null as number | null,
    is_featured: false,
    is_active: true,
});

const submit = () => {
    form.post(admin.events.store().url, {
        forceFormData: true,
    });
};

const handleImageChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.image = input.files?.[0] ?? null;
};
</script>

<template>
    <Head title="Nuevo evento" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo evento"
            description="Crea un nuevo evento o actividad para el zoológico."
            section="Eventos"
            :icon="CalendarDays"
            :action-icon="ArrowLeft"
            action-label="Volver a eventos"
            :action-href="admin.events.index().url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Información general -->
            <AdminCard
                title="Información general"
                description="Define los datos principales del evento."
                :icon="Info"
            >
                <div class="space-y-5">
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre <span class="form-required">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej. Alimentación de jirafas"
                            autocomplete="off"
                            required
                            :class="[
                                'form-input',
                                form.errors.name && 'form-input-error',
                            ]"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="type" class="form-label">
                                Tipo de evento
                            </label>

                            <input
                                id="type"
                                v-model="form.type"
                                type="text"
                                placeholder="Ej. Educativo"
                                :class="[
                                    'form-input',
                                    form.errors.type && 'form-input-error',
                                ]"
                            />

                            <p v-if="form.errors.type" class="form-error">
                                {{ form.errors.type }}
                            </p>
                        </div>

                        <div class="form-field">
                            <label for="zoo_zone_id" class="form-label">
                                <span class="inline-flex items-center gap-2">
                                    <MapPin :size="16" />
                                    Zona
                                </span>
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="zoo_zone_id"
                                    v-model="form.zoo_zone_id"
                                    :class="[
                                        'form-select appearance-none',
                                        form.errors.zoo_zone_id &&
                                            'form-select-error',
                                    ]"
                                >
                                    <option value="">Sin zona</option>

                                    <option
                                        v-for="zone in props.zones"
                                        :key="zone.id"
                                        :value="String(zone.id)"
                                    >
                                        {{ zone.name }}
                                    </option>
                                </select>

                                <ChevronDown
                                    :size="17"
                                    class="form-select-icon"
                                />
                            </div>

                            <p
                                v-if="form.errors.zoo_zone_id"
                                class="form-error"
                            >
                                {{ form.errors.zoo_zone_id }}
                            </p>
                        </div>
                    </div>
                </div>
            </AdminCard>

            <!-- Fecha y capacidad -->
            <AdminCard
                title="Fecha y capacidad"
                description="Configura cuándo se realizará y cuántas personas pueden asistir."
                :icon="Clock"
            >
                <div class="space-y-5">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="start_at" class="form-label">
                                Fecha y hora de inicio
                                <span class="form-required">*</span>
                            </label>

                            <input
                                id="start_at"
                                v-model="form.start_at"
                                type="datetime-local"
                                required
                                :class="[
                                    'form-input',
                                    form.errors.start_at && 'form-input-error',
                                ]"
                            />

                            <p
                                v-if="form.errors.start_at"
                                class="form-error"
                            >
                                {{ form.errors.start_at }}
                            </p>
                        </div>

                        <div class="form-field">
                            <label for="end_at" class="form-label">
                                Fecha y hora de finalización
                            </label>

                            <input
                                id="end_at"
                                v-model="form.end_at"
                                type="datetime-local"
                                :class="[
                                    'form-input',
                                    form.errors.end_at && 'form-input-error',
                                ]"
                            />

                            <p class="form-help">
                                Déjalo vacío si el evento no tiene hora de
                                finalización.
                            </p>

                            <p v-if="form.errors.end_at" class="form-error">
                                {{ form.errors.end_at }}
                            </p>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="capacity" class="form-label">
                            <span class="inline-flex items-center gap-2">
                                <Users :size="16" />
                                Capacidad
                            </span>
                        </label>

                        <input
                            id="capacity"
                            v-model.number="form.capacity"
                            type="number"
                            min="1"
                            placeholder="Ej. 50"
                            :class="[
                                'form-input',
                                form.errors.capacity && 'form-input-error',
                            ]"
                        />

                        <p class="form-help">
                            Déjalo vacío si el evento no tiene límite de
                            asistentes.
                        </p>

                        <p v-if="form.errors.capacity" class="form-error">
                            {{ form.errors.capacity }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Contenido -->
            <AdminCard
                title="Contenido"
                description="Agrega información y material visual del evento."
                :icon="FileImage"
            >
                <div class="space-y-5">
                    <div class="form-field">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe el evento..."
                            :class="[
                                'form-textarea',
                                form.errors.description &&
                                    'form-textarea-error',
                            ]"
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="image" class="form-label">
                            Imagen del evento
                        </label>

                        <div class="form-upload">
                            <div class="form-upload-icon">
                                <FileImage :size="22" />
                            </div>

                            <p class="form-upload-title">
                                {{ form.image?.name ?? 'Selecciona una imagen' }}
                            </p>

                            <p class="form-upload-description">
                                JPG, JPEG, PNG o WEBP. Máximo 5 MB.
                            </p>

                            <input
                                id="image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                class="form-upload-input"
                                @change="handleImageChange"
                            />
                        </div>

                        <p v-if="form.errors.image" class="form-error">
                            {{ form.errors.image }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Define la visibilidad y el estado del evento."
                :icon="Star"
            >
                <div class="form-grid">
                    <label class="form-checkbox-row cursor-pointer">
                        <input
                            v-model="form.is_featured"
                            type="checkbox"
                            class="form-checkbox"
                        />

                        <span>
                            <span class="form-checkbox-title block">
                                Evento destacado
                            </span>
                            <span class="form-checkbox-description block">
                                Marca el evento para destacarlo frente a los
                                demás.
                            </span>
                        </span>
                    </label>

                    <label class="form-checkbox-row cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="form-checkbox"
                        />

                        <span>
                            <span class="form-checkbox-title block">
                                Evento activo
                            </span>
                            <span class="form-checkbox-description block">
                                El evento estará disponible en el sistema.
                            </span>
                        </span>
                    </label>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col gap-4 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center"
            >
                <p class="form-help sm:mr-auto">
                    <span class="form-required">*</span>
                    Campos obligatorios
                </p>

                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <CancelButton
                        :href="admin.events.index().url"
                        :icon="X"
                        label="Cancelar"
                    />

                    <AdminButton
                        type="submit"
                        variant="primary"
                        :icon="Save"
                        :loading="form.processing"
                        loading-text="Guardando..."
                        :disabled="form.processing"
                    >
                        Guardar evento
                    </AdminButton>
                </div>
            </div>
        </form>
    </div>
</template>