<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Box,
    ChevronDown,
    ExternalLink,
    FileBox,
    FileImage,
    Info,
    Layers3,
    Save,
    Tag,
    Trash2,
    Upload,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';

interface Species {
    id: number;
    common_name: string;
    scientific_name: string;
}

interface Card {
    id: number;
    species_id: number;
    name: string;
    rarity: string;
    edition: string | null;
    description: string | null;
    card_image: string | null;
    model_name: string | null;
    model_file: string | null;
    model_url: string | null;
    model_format: string | null;
    model_description: string | null;
    is_active: boolean;
    sort_order: number;
    species?: Species;
}

const props = defineProps<{
    card: Card;
    species: Species[];
}>();

const form = useForm({
    species_id: String(props.card.species_id),
    name: props.card.name ?? '',
    rarity: props.card.rarity ?? 'comun',
    edition: props.card.edition ?? '',
    description: props.card.description ?? '',
    card_image: null as File | null,
    model_name: props.card.model_name ?? '',
    model_file: null as File | null,
    model_url: props.card.model_url ?? '',
    model_format: props.card.model_format ?? '',
    model_description: props.card.model_description ?? '',
    is_active: props.card.is_active,
    sort_order: props.card.sort_order ?? 0,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(admin.cards.update(props.card.id).url, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const cardImageUrl = (path: string | null): string | null => {
    if (!path) return null;

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};

const modelFileUrl = (path: string | null): string | null => {
    if (!path) return null;

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};

const handleCardImage = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.card_image = target.files?.[0] ?? null;
};

const handleModelFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.model_file = target.files?.[0] ?? null;
};

const deleteImage = () => {
    Swal.fire({
        title: '¿Eliminar imagen?',
        text: 'La imagen actual de esta tarjeta será eliminada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(admin.cards.image.destroy(props.card.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.card_image = null;
            },
        });
    });
};

const deleteModel = () => {
    Swal.fire({
        title: '¿Eliminar modelo 3D?',
        text: 'El archivo 3D actual de esta tarjeta será eliminado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(admin.cards.model.destroy(props.card.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.model_file = null;
            },
        });
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Tarjetas',
                href: admin.cards.index(),
            },
            {
                title: 'Editar tarjeta',
                href: admin.cards.edit,
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar tarjeta: ${card.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar tarjeta"
            :description="`Actualiza la información de ${card.name}.`"
            section="Colección"
            :icon="Tag"
            :action-icon="ArrowLeft"
            action-label="Volver a tarjetas"
            :action-href="admin.cards.index().url"
        />

        <form
            id="card-form"
            class="space-y-5"
            @submit.prevent="submit"
        >
            <!-- Información general -->
            <AdminCard
                title="Información general"
                description="Modifica los datos principales e identificación de la tarjeta."
                :icon="Info"
            >
                <div class="space-y-5">
                    <!-- Especie -->
                    <div class="form-field">
                        <label for="species_id" class="form-label">
                            Especie <span class="form-required">*</span>
                        </label>

                        <div class="form-select-wrapper">
                            <select
                                id="species_id"
                                v-model="form.species_id"
                                class="form-select appearance-none"
                            >
                                <option value="">
                                    Selecciona una especie
                                </option>

                                <option
                                    v-for="item in species"
                                    :key="item.id"
                                    :value="String(item.id)"
                                >
                                    {{ item.common_name }} —
                                    {{ item.scientific_name }}
                                </option>
                            </select>

                            <ChevronDown
                                :size="16"
                                class="form-select-icon"
                            />
                        </div>

                        <p
                            v-if="form.errors.species_id"
                            class="form-error"
                        >
                            {{ form.errors.species_id }}
                        </p>
                    </div>

                    <div class="form-grid">
                        <!-- Nombre -->
                        <div class="form-field">
                            <label for="name" class="form-label">
                                Nombre de la tarjeta
                                <span class="form-required">*</span>
                            </label>

                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Ej. Jaguar del Norte"
                                class="form-input"
                            />

                            <p v-if="form.errors.name" class="form-error">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Rareza -->
                        <div class="form-field">
                            <label for="rarity" class="form-label">
                                Rareza <span class="form-required">*</span>
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="rarity"
                                    v-model="form.rarity"
                                    class="form-select appearance-none"
                                >
                                    <option value="comun">Común</option>
                                    <option value="rara">Rara</option>
                                    <option value="epica">Épica</option>
                                    <option value="edicion_especial">
                                        Edición especial
                                    </option>
                                </select>

                                <ChevronDown
                                    :size="16"
                                    class="form-select-icon"
                                />
                            </div>

                            <p
                                v-if="form.errors.rarity"
                                class="form-error"
                            >
                                {{ form.errors.rarity }}
                            </p>
                        </div>

                        <!-- Edición -->
                        <div class="form-field">
                            <label for="edition" class="form-label">
                                Edición
                            </label>

                            <input
                                id="edition"
                                v-model="form.edition"
                                type="text"
                                placeholder="Ej. Colección Sahuatoba 2026"
                                class="form-input"
                            />

                            <p class="form-help">
                                Opcional. Identifica la colección a la que
                                pertenece.
                            </p>

                            <p
                                v-if="form.errors.edition"
                                class="form-error"
                            >
                                {{ form.errors.edition }}
                            </p>
                        </div>

                        <!-- Orden -->
                        <div class="form-field">
                            <label for="sort_order" class="form-label">
                                Orden de aparición
                            </label>

                            <input
                                id="sort_order"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="form-input"
                            />

                            <p class="form-help">
                                Un número menor aparece primero.
                            </p>

                            <p
                                v-if="form.errors.sort_order"
                                class="form-error"
                            >
                                {{ form.errors.sort_order }}
                            </p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="form-field">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe las características de esta tarjeta..."
                            class="form-textarea"
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Imagen de la tarjeta -->
            <AdminCard
                title="Imagen de la tarjeta"
                description="Conserva la imagen actual o reemplázala por una nueva."
                :icon="FileImage"
            >
                <div class="space-y-5">
                    <!-- Imagen actual -->
                    <div
                        v-if="card.card_image"
                        class="flex flex-col gap-5 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4 sm:flex-row sm:items-start"
                    >
                        <img
                            :src="cardImageUrl(card.card_image) ?? ''"
                            :alt="card.name"
                            class="h-44 w-32 shrink-0 rounded-lg border border-[#DCE7DF] bg-white object-cover"
                        />

                        <div
                            class="flex min-w-0 flex-1 flex-col items-start gap-4"
                        >
                            <div>
                                <p
                                    class="flex items-center gap-2 text-sm font-semibold text-[#244334]"
                                >
                                    <FileImage :size="16" />
                                    Imagen actual
                                </p>

                                <p
                                    class="mt-2 break-all text-xs text-[#718477]"
                                >
                                    {{ card.card_image }}
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="form.processing"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="deleteImage"
                            >
                                <Trash2 :size="16" />
                                Eliminar imagen
                            </button>
                        </div>
                    </div>

                    <!-- Nueva imagen -->
                    <div class="form-field">
                        <label for="card_image" class="form-label">
                            {{
                                card.card_image
                                    ? 'Reemplazar imagen'
                                    : 'Imagen de la tarjeta'
                            }}
                        </label>

                        <label for="card_image" class="form-upload">
                            <div class="form-upload-icon">
                                <Upload :size="22" />
                            </div>

                            <span class="form-upload-title break-all">
                                {{
                                    form.card_image?.name ||
                                    'Selecciona una nueva imagen'
                                }}
                            </span>

                            <span class="form-upload-description">
                                Haz clic para buscar un archivo en tu
                                dispositivo.
                            </span>

                            <span class="form-upload-description">
                                JPG, JPEG, PNG o WEBP · Máximo 5 MB
                            </span>

                            <input
                                id="card_image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                class="form-upload-input"
                                @change="handleCardImage"
                            />
                        </label>

                        <p
                            v-if="form.errors.card_image"
                            class="form-error"
                        >
                            {{ form.errors.card_image }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Modelo 3D -->
            <AdminCard
                title="Modelo 3D"
                description="Administra el modelo 3D asociado a esta tarjeta."
                :icon="Box"
            >
                <div class="space-y-5">
                    <!-- Modelo actual -->
                    <div
                        v-if="card.model_file || card.model_url"
                        class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4 sm:p-5"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
                            >
                                <FileBox :size="20" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-semibold text-[#244334]"
                                >
                                    Modelo actual
                                </p>

                                <p
                                    v-if="card.model_name"
                                    class="mt-1 text-sm text-[#718477]"
                                >
                                    {{ card.model_name }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div
                                v-if="card.model_format"
                                class="rounded-lg border border-[#DCE7DF] bg-white p-3"
                            >
                                <p class="text-xs text-[#718477]">
                                    Formato
                                </p>

                                <p
                                    class="mt-1 text-sm font-semibold text-[#244334]"
                                >
                                    {{ card.model_format.toUpperCase() }}
                                </p>
                            </div>

                            <div
                                v-if="card.model_file"
                                class="min-w-0 rounded-lg border border-[#DCE7DF] bg-white p-3"
                            >
                                <p class="text-xs text-[#718477]">
                                    Archivo
                                </p>

                                <a
                                    :href="modelFileUrl(card.model_file) ?? ''"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-1 flex items-start gap-1 break-all text-sm font-medium text-[#187744] hover:underline"
                                >
                                    <span>{{ card.model_file }}</span>
                                    <ExternalLink
                                        :size="14"
                                        class="mt-0.5 shrink-0"
                                    />
                                </a>
                            </div>

                            <div
                                v-if="card.model_url"
                                class="min-w-0 rounded-lg border border-[#DCE7DF] bg-white p-3 sm:col-span-2"
                            >
                                <p class="text-xs text-[#718477]">
                                    URL externa
                                </p>

                                <a
                                    :href="card.model_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-1 flex items-start gap-1 break-all text-sm font-medium text-[#187744] hover:underline"
                                >
                                    <span>{{ card.model_url }}</span>
                                    <ExternalLink
                                        :size="14"
                                        class="mt-0.5 shrink-0"
                                    />
                                </a>
                            </div>
                        </div>

                        <button
                            v-if="card.model_file"
                            type="button"
                            :disabled="form.processing"
                            class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="deleteModel"
                        >
                            <Trash2 :size="16" />
                            Eliminar archivo 3D
                        </button>
                    </div>

                    <div class="form-grid">
                        <!-- Nombre del modelo -->
                        <div class="form-field">
                            <label for="model_name" class="form-label">
                                Nombre del modelo
                            </label>

                            <input
                                id="model_name"
                                v-model="form.model_name"
                                type="text"
                                placeholder="Ej. Jaguar 3D"
                                class="form-input"
                            />

                            <p
                                v-if="form.errors.model_name"
                                class="form-error"
                            >
                                {{ form.errors.model_name }}
                            </p>
                        </div>

                        <!-- Formato -->
                        <div class="form-field">
                            <label for="model_format" class="form-label">
                                Formato del modelo
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="model_format"
                                    v-model="form.model_format"
                                    class="form-select appearance-none"
                                >
                                    <option value="">
                                        Seleccionar formato
                                    </option>
                                    <option value="glb">GLB</option>
                                    <option value="gltf">GLTF</option>
                                </select>

                                <ChevronDown
                                    :size="16"
                                    class="form-select-icon"
                                />
                            </div>

                            <p
                                v-if="form.errors.model_format"
                                class="form-error"
                            >
                                {{ form.errors.model_format }}
                            </p>
                        </div>
                    </div>

                    <!-- Archivo 3D -->
                    <div class="form-field">
                        <label for="model_file" class="form-label">
                            {{
                                card.model_file
                                    ? 'Reemplazar archivo 3D'
                                    : 'Archivo del modelo'
                            }}
                        </label>

                        <label
                            for="model_file"
                            class="form-upload sm:flex-row sm:text-left"
                        >
                            <div class="form-upload-icon">
                                <FileBox :size="22" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="break-all text-sm font-medium text-[#244334]"
                                >
                                    {{
                                        form.model_file?.name ||
                                        'Seleccionar archivo 3D'
                                    }}
                                </p>

                                <p class="form-upload-description mt-1">
                                    Formatos GLB o GLTF · Máximo 50 MB
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-4 py-2.5 text-sm font-medium text-[#435D4C] transition group-hover:border-[#A9CBB5]"
                            >
                                <Upload :size="16" />
                                Examinar
                            </span>

                            <input
                                id="model_file"
                                type="file"
                                accept=".glb,.gltf"
                                class="form-upload-input"
                                @change="handleModelFile"
                            />
                        </label>

                        <p
                            v-if="form.errors.model_file"
                            class="form-error"
                        >
                            {{ form.errors.model_file }}
                        </p>
                    </div>

                    <!-- URL -->
                    <div class="form-field">
                        <label for="model_url" class="form-label">
                            URL del modelo 3D
                        </label>

                        <input
                            id="model_url"
                            v-model="form.model_url"
                            type="url"
                            placeholder="https://ejemplo.com/modelo.glb"
                            class="form-input"
                        />

                        <p class="form-help">
                            Opcional. Utiliza este campo si el modelo está
                            alojado externamente.
                        </p>

                        <p
                            v-if="form.errors.model_url"
                            class="form-error"
                        >
                            {{ form.errors.model_url }}
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div class="form-field">
                        <label
                            for="model_description"
                            class="form-label"
                        >
                            Descripción del modelo
                        </label>

                        <textarea
                            id="model_description"
                            v-model="form.model_description"
                            rows="3"
                            placeholder="Información adicional sobre el modelo 3D..."
                            class="form-textarea"
                        />

                        <p
                            v-if="form.errors.model_description"
                            class="form-error"
                        >
                            {{ form.errors.model_description }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Controla la disponibilidad de la tarjeta."
                :icon="Layers3"
            >
                <ActiveSwitch
                    id="is_active"
                    v-model="form.is_active"
                    label="Tarjeta activa"
                    description="La tarjeta estará disponible para mostrarse y utilizarse en el sistema."
                    :error="form.errors.is_active"
                />
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col gap-3 rounded-2xl border border-[#DCE7DF] bg-white p-4 sm:flex-row sm:items-center sm:justify-end sm:px-6"
            >
                <p class="form-help sm:mr-auto">
                    Actualiza los campos necesarios antes de guardar.
                </p>

                <CancelButton
                    :href="admin.cards.index().url"
                    :icon="ArrowLeft"
                >
                    Cancelar
                </CancelButton>

                <AdminButton
                    type="submit"
                    :disabled="form.processing"
                    :loading="form.processing"
                    loading-text="Guardando cambios..."
                    :icon="Save"
                >
                    Guardar cambios
                </AdminButton>
            </div>
        </form>
    </div>
</template>