
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Box,
    ChevronDown,
    FileBox,
    FileImage,
    Info,
    Layers3,
    Save,
    Tag,
    Upload,
} from 'lucide-vue-next';

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

const props = defineProps<{
    species: Species[];
}>();

const form = useForm({
    species_id: '',
    name: '',
    rarity: 'comun',
    edition: '',
    description: '',
    card_image: null as File | null,
    model_name: '',
    model_file: null as File | null,
    model_url: '',
    model_format: '',
    model_description: '',
    is_active: true,
    sort_order: 0,
});

const submit = () => {
    form.post(admin.cards.store().url, {
        forceFormData: true,
    });
};

const handleCardImage = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.card_image = target.files?.[0] ?? null;
};

const handleModelFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.model_file = target.files?.[0] ?? null;
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
                href: admin.cards.index,
            },
            {
                title: 'Agregar tarjeta',
                href: admin.cards.create,
            },
        ],
    },
});
</script>

<template>
    <Head title="Nueva tarjeta" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Encabezado -->
        <PageHeader
            title="Nueva tarjeta"
            description="Crea una nueva tarjeta coleccionable para una especie."
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
                description="Datos principales e identificación de la tarjeta."
                :icon="Info"
            >
                <div class="space-y-5">
                    <!-- Especie -->
                    <div class="form-field">
                        <label for="species_id" class="form-label">
                            Especie
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="form-select-wrapper">
                            <select
                                id="species_id"
                                v-model="form.species_id"
                                class="form-select appearance-none"
                                :class="{
                                    'form-input-error': form.errors.species_id,
                                }"
                            >
                                <option value="">
                                    Selecciona una especie
                                </option>

                                <option
                                    v-for="item in props.species"
                                    :key="item.id"
                                    :value="String(item.id)"
                                >
                                    {{ item.common_name }} — {{ item.scientific_name }}
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
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Ej. Jaguar del Norte"
                                class="form-input"
                                :class="{
                                    'form-input-error': form.errors.name,
                                }"
                            />

                            <p v-if="form.errors.name" class="form-error">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Rareza -->
                        <div class="form-field">
                            <label for="rarity" class="form-label">
                                Rareza
                                <span class="text-red-500">*</span>
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="rarity"
                                    v-model="form.rarity"
                                    class="form-select appearance-none"
                                    :class="{
                                        'form-input-error': form.errors.rarity,
                                    }"
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

                            <p v-if="form.errors.rarity" class="form-error">
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
                                :class="{
                                    'form-input-error': form.errors.edition,
                                }"
                            />

                            <p class="form-help">
                                Opcional. Identifica la colección a la que pertenece.
                            </p>

                            <p v-if="form.errors.edition" class="form-error">
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
                                :class="{
                                    'form-input-error': form.errors.sort_order,
                                }"
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
                            :class="{
                                'form-textarea-error': form.errors.description,
                            }"
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
                description="Sube la imagen que se mostrará en la colección."
                :icon="FileImage"
            >
                <div>
                    <label for="card_image" class="form-upload">
                        <div class="form-upload-icon">
                            <Upload :size="24" />
                        </div>

                        <span class="form-upload-title">
                            {{ form.card_image?.name || 'Selecciona una imagen' }}
                        </span>

                        <span class="form-upload-description">
                            Haz clic para buscar un archivo en tu dispositivo.
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
                        class="form-error mt-3"
                    >
                        {{ form.errors.card_image }}
                    </p>
                </div>
            </AdminCard>

            <!-- Modelo 3D -->
            <AdminCard
                title="Modelo 3D"
                description="Añade un modelo interactivo asociado a la tarjeta."
                :icon="Box"
            >
                <div class="space-y-5">
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
                                :class="{
                                    'form-input-error': form.errors.model_name,
                                }"
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
                                    :class="{
                                        'form-input-error': form.errors.model_format,
                                    }"
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

                    <!-- Archivo del modelo -->
                    <div class="form-field">
                        <label for="model_file" class="form-label">
                            Archivo del modelo
                        </label>

                        <label
                            for="model_file"
                            class="form-upload sm:flex-row sm:text-left"
                        >
                            <div class="form-upload-icon">
                                <FileBox :size="22" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm font-medium text-[#244334]">
                                    {{ form.model_file?.name || 'Seleccionar archivo 3D' }}
                                </p>

                                <p class="form-upload-description mt-1">
                                    Formatos GLB o GLTF · Máximo 50 MB
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-4 py-2.5 text-sm font-medium text-[#435D4C] transition hover:bg-[#F2F7F4]"
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

                    <!-- URL del modelo -->
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
                            :class="{
                                'form-input-error': form.errors.model_url,
                            }"
                        />

                        <p class="form-help">
                            Opcional. Utiliza este campo si el modelo está alojado externamente.
                        </p>

                        <p
                            v-if="form.errors.model_url"
                            class="form-error"
                        >
                            {{ form.errors.model_url }}
                        </p>
                    </div>

                    <!-- Descripción del modelo -->
                    <div class="form-field">
                        <label for="model_description" class="form-label">
                            Descripción del modelo
                        </label>

                        <textarea
                            id="model_description"
                            v-model="form.model_description"
                            rows="3"
                            placeholder="Información adicional sobre el modelo 3D..."
                            class="form-textarea"
                            :class="{
                                'form-textarea-error': form.errors.model_description,
                            }"
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
                description="Define la disponibilidad y el orden de la tarjeta."
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

            <!-- Acciones: pie opcional -->
            <AdminCard :padding="false">
                <template #default>
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                        <p class="form-help sm:mr-auto">
                            <span class="text-red-500">*</span>
                            Campos obligatorios según la validación del formulario.
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
                            :icon="Save"
                            loading-text="Guardando..."
                        >
                            Guardar tarjeta
                        </AdminButton>
                    </div>
                </template>
            </AdminCard>
        </form>
    </div>
</template>
