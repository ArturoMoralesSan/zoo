<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    FileImage,
    Info,
    MapPin,
    Save,
    Star,
    Users,
    X,
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
        { title: 'Editar evento', href: '' },
    ],
});

interface Zone {
    id: number;
    name: string;
}

interface EventItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    type: string | null;
    start_at: string | null;
    end_at: string | null;
    zoo_zone_id: number | null;
    image: string | null;
    capacity: number | null;
    is_featured: boolean;
    is_active: boolean;
}

const props = defineProps<{
    event: EventItem;
    zones: Zone[];
}>();

const formatDateTimeLocal = (value: string | null): string => {
    if (!value) return '';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return '';

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const form = useForm({
    name: props.event.name,
    description: props.event.description ?? '',
    type: props.event.type ?? '',
    start_at: formatDateTimeLocal(props.event.start_at),
    end_at: formatDateTimeLocal(props.event.end_at),
    zoo_zone_id:
        props.event.zoo_zone_id !== null
            ? String(props.event.zoo_zone_id)
            : '',
    image: null as File | null,
    capacity: props.event.capacity,
    is_featured: props.event.is_featured,
    is_active: props.event.is_active,
    remove_image: false,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'put',
    })).post(admin.events.update(props.event.id).url, {
        forceFormData: true,
    });
};

const handleImageChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.image = input.files?.[0] ?? null;
};

const restoreCurrentImage = () => {
    form.remove_image = false;
};
</script>

<template>
    <Head :title="`Editar ${event.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar evento"
            :description="`Actualiza la información del evento «${event.name}».`"
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
                description="Actualiza los datos principales del evento."
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
                description="Actualiza el horario y la capacidad del evento."
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
                description="Actualiza la descripción y el material visual."
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

                    <!-- Imagen actual -->
                    <div class="form-field">
                        <label class="form-label">Imagen del evento</label>

                        <div
                            v-if="event.image && !form.remove_image"
                            class="form-preview"
                        >
                            <div class="p-4">
                                <p class="form-help mb-3">Imagen actual</p>

                                <img
                                    :src="`/storage/${event.image}`"
                                    :alt="event.name"
                                    class="form-preview-image"
                                />
                            </div>

                            <div class="form-preview-footer">
                                <p class="form-help">
                                    Esta imagen está guardada actualmente.
                                </p>

                                <button
                                    type="button"
                                    class="form-remove-button"
                                    title="Eliminar imagen actual"
                                    aria-label="Eliminar imagen actual"
                                    @click="form.remove_image = true"
                                >
                                    <X :size="18" />
                                </button>
                            </div>
                        </div>

                        <!-- Imagen marcada para eliminar -->
                        <div
                            v-else-if="event.image && form.remove_image"
                            class="form-upload-selected"
                        >
                            <div class="flex items-start gap-3">
                                <div class="form-upload-icon">
                                    <FileImage :size="20" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="form-upload-title">
                                        Imagen marcada para eliminar
                                    </p>

                                    <p class="form-upload-description">
                                        La imagen actual se eliminará al guardar
                                        los cambios.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="form-button form-button-secondary"
                                    @click="restoreCurrentImage"
                                >
                                    <ArrowLeft :size="16" />
                                    Conservar
                                </button>
                            </div>
                        </div>

                        <!-- Seleccionar imagen nueva -->
                        <div class="form-upload">
                            <div class="form-upload-icon">
                                <FileImage :size="22" />
                            </div>

                            <p class="form-upload-title">
                                {{
                                    form.image?.name ??
                                    'Selecciona una imagen nueva'
                                }}
                            </p>

                            <p class="form-upload-description">
                                JPG, JPEG, PNG o WEBP. Máximo 5 MB.
                                Selecciona una imagen solo si deseas reemplazar
                                la actual.
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

                        <p v-if="form.errors.remove_image" class="form-error">
                            {{ form.errors.remove_image }}
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
                        loading-text="Actualizando..."
                        :disabled="form.processing"
                    >
                        Actualizar evento
                    </AdminButton>
                </div>
            </div>
        </form>
    </div>
</template>