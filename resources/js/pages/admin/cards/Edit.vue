<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

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
    });
};

const cardImageUrl = (path: string | null): string | null => {
    if (!path) {
        return null;
    }

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
    if (!path) {
        return null;
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};

const deleteImage = () => {
    if (!confirm('¿Deseas eliminar la imagen de esta tarjeta?')) {
        return;
    }

    form.delete(admin.cards.image.destroy(props.card.id).url);
};

const deleteModel = () => {
    if (!confirm('¿Deseas eliminar el modelo 3D de esta tarjeta?')) {
        return;
    }

    form.delete(admin.cards.model.destroy(props.card.id).url);
};
</script>

<template>
    <Head :title="`Editar tarjeta: ${card.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h1 class="text-2xl font-semibold">
                    Editar tarjeta
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Actualiza la información, imagen y modelo 3D de la tarjeta.
                </p>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                @submit.prevent="submit"
                class="space-y-6"
            >
                <!-- Especie -->
                <div class="space-y-2">
                    <label
                        for="species_id"
                        class="text-sm font-medium"
                    >
                        Especie
                    </label>

                    <select
                        id="species_id"
                        v-model="form.species_id"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">
                            Selecciona una especie
                        </option>

                        <option
                            v-for="item in props.species"
                            :key="item.id"
                            :value="String(item.id)"
                        >
                            {{ item.common_name }} —
                            {{ item.scientific_name }}
                        </option>
                    </select>

                    <p
                        v-if="form.errors.species_id"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.species_id }}
                    </p>
                </div>

                <!-- Nombre y rareza -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Nombre -->
                    <div class="space-y-2">
                        <label
                            for="name"
                            class="text-sm font-medium"
                        >
                            Nombre de la tarjeta
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej. Jaguar del Norte"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p
                            v-if="form.errors.name"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Rareza -->
                    <div class="space-y-2">
                        <label
                            for="rarity"
                            class="text-sm font-medium"
                        >
                            Rareza
                        </label>

                        <select
                            id="rarity"
                            v-model="form.rarity"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="comun">
                                Común
                            </option>

                            <option value="rara">
                                Rara
                            </option>

                            <option value="epica">
                                Épica
                            </option>

                            <option value="edicion_especial">
                                Edición especial
                            </option>
                        </select>

                        <p
                            v-if="form.errors.rarity"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.rarity }}
                        </p>
                    </div>
                </div>

                <!-- Edición y orden -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Edición -->
                    <div class="space-y-2">
                        <label
                            for="edition"
                            class="text-sm font-medium"
                        >
                            Edición
                        </label>

                        <input
                            id="edition"
                            v-model="form.edition"
                            type="text"
                            placeholder="Ej. Colección Sahuatoba 2026"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p
                            v-if="form.errors.edition"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.edition }}
                        </p>
                    </div>

                    <!-- Orden -->
                    <div class="space-y-2">
                        <label
                            for="sort_order"
                            class="text-sm font-medium"
                        >
                            Orden
                        </label>

                        <input
                            id="sort_order"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p
                            v-if="form.errors.sort_order"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.sort_order }}
                        </p>
                    </div>
                </div>

                <!-- Descripción -->
                <div class="space-y-2">
                    <label
                        for="description"
                        class="text-sm font-medium"
                    >
                        Descripción
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        placeholder="Describe la tarjeta..."
                        class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    ></textarea>

                    <p
                        v-if="form.errors.description"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Imagen -->
                <div
                    class="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div class="mb-5">
                        <h2 class="text-base font-semibold">
                            Imagen de la tarjeta
                        </h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Puedes conservar la imagen actual o reemplazarla.
                        </p>
                    </div>

                    <!-- Imagen actual -->
                    <div
                        v-if="card.card_image"
                        class="mb-5 flex flex-col gap-4 rounded-lg border border-sidebar-border p-4 sm:flex-row sm:items-start"
                    >
                        <img
                            :src="cardImageUrl(card.card_image) ?? ''"
                            :alt="card.name"
                            class="h-40 w-40 rounded-lg border border-sidebar-border object-cover"
                        />

                        <div class="flex flex-1 flex-col gap-3">
                            <div>
                                <p class="text-sm font-medium">
                                    Imagen actual
                                </p>

                                <p class="mt-1 break-all text-xs text-muted-foreground">
                                    {{ card.card_image }}
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="form.processing"
                                class="inline-flex w-fit items-center justify-center rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                                @click="deleteImage"
                            >
                                Eliminar imagen
                            </button>
                        </div>
                    </div>

                    <!-- Nueva imagen -->
                    <div class="space-y-2">
                        <label
                            for="card_image"
                            class="text-sm font-medium"
                        >
                            {{ card.card_image ? 'Reemplazar imagen' : 'Imagen' }}
                        </label>

                        <input
                            id="card_image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition file:mr-4 file:border-0 file:bg-muted file:px-4 file:py-2 file:text-sm"
                            @change="
                                form.card_image =
                                    ($event.target as HTMLInputElement).files?.[0] ?? null
                            "
                        />

                        <p class="text-xs text-muted-foreground">
                            JPG, JPEG, PNG o WEBP. Máximo 5 MB.
                        </p>

                        <p
                            v-if="form.errors.card_image"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.card_image }}
                        </p>
                    </div>
                </div>

                <!-- Modelo 3D -->
                <div
                    class="rounded-lg border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div class="mb-5">
                        <h2 class="text-base font-semibold">
                            Modelo 3D
                        </h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra el modelo 3D asociado a esta tarjeta.
                        </p>
                    </div>

                    <!-- Modelo actual -->
                    <div
                        v-if="card.model_file || card.model_url"
                        class="mb-6 rounded-lg border border-sidebar-border p-4"
                    >
                        <p class="text-sm font-medium">
                            Modelo actual
                        </p>

                        <div class="mt-3 space-y-2 text-sm">
                            <p v-if="card.model_name">
                                <span class="font-medium">
                                    Nombre:
                                </span>
                                {{ card.model_name }}
                            </p>

                            <p v-if="card.model_format">
                                <span class="font-medium">
                                    Formato:
                                </span>
                                {{ card.model_format.toUpperCase() }}
                            </p>

                            <p v-if="card.model_file">
                                <span class="font-medium">
                                    Archivo:
                                </span>

                                <a
                                    :href="modelFileUrl(card.model_file) ?? ''"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="break-all text-primary hover:underline"
                                >
                                    {{ card.model_file }}
                                </a>
                            </p>

                            <p v-if="card.model_url">
                                <span class="font-medium">
                                    URL:
                                </span>

                                <a
                                    :href="card.model_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="break-all text-primary hover:underline"
                                >
                                    {{ card.model_url }}
                                </a>
                            </p>
                        </div>

                        <button
                            v-if="card.model_file"
                            type="button"
                            :disabled="form.processing"
                            class="mt-4 inline-flex items-center justify-center rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
                            @click="deleteModel"
                        >
                            Eliminar archivo 3D
                        </button>
                    </div>

                    <div class="space-y-6">
                        <!-- Nombre y formato -->
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Nombre -->
                            <div class="space-y-2">
                                <label
                                    for="model_name"
                                    class="text-sm font-medium"
                                >
                                    Nombre del modelo
                                </label>

                                <input
                                    id="model_name"
                                    v-model="form.model_name"
                                    type="text"
                                    placeholder="Ej. Jaguar 3D"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="form.errors.model_name"
                                    class="text-sm text-red-500"
                                >
                                    {{ form.errors.model_name }}
                                </p>
                            </div>

                            <!-- Formato -->
                            <div class="space-y-2">
                                <label
                                    for="model_format"
                                    class="text-sm font-medium"
                                >
                                    Formato
                                </label>

                                <select
                                    id="model_format"
                                    v-model="form.model_format"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">
                                        Seleccionar formato
                                    </option>

                                    <option value="glb">
                                        GLB
                                    </option>

                                    <option value="gltf">
                                        GLTF
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors.model_format"
                                    class="text-sm text-red-500"
                                >
                                    {{ form.errors.model_format }}
                                </p>
                            </div>
                        </div>

                        <!-- Nuevo archivo -->
                        <div class="space-y-2">
                            <label
                                for="model_file"
                                class="text-sm font-medium"
                            >
                                {{ card.model_file ? 'Reemplazar archivo 3D' : 'Archivo 3D' }}
                            </label>

                            <input
                                id="model_file"
                                type="file"
                                accept=".glb,.gltf"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition file:mr-4 file:border-0 file:bg-muted file:px-4 file:py-2 file:text-sm"
                                @change="
                                    form.model_file =
                                        ($event.target as HTMLInputElement).files?.[0] ?? null
                                "
                            />

                            <p class="text-xs text-muted-foreground">
                                GLB o GLTF. Máximo 50 MB.
                            </p>

                            <p
                                v-if="form.errors.model_file"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.model_file }}
                            </p>
                        </div>

                        <!-- URL -->
                        <div class="space-y-2">
                            <label
                                for="model_url"
                                class="text-sm font-medium"
                            >
                                URL del modelo 3D
                            </label>

                            <input
                                id="model_url"
                                v-model="form.model_url"
                                type="url"
                                placeholder="https://..."
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p class="text-xs text-muted-foreground">
                                Opcional. Puedes utilizar un modelo alojado externamente.
                            </p>

                            <p
                                v-if="form.errors.model_url"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.model_url }}
                            </p>
                        </div>

                        <!-- Descripción -->
                        <div class="space-y-2">
                            <label
                                for="model_description"
                                class="text-sm font-medium"
                            >
                                Descripción del modelo
                            </label>

                            <textarea
                                id="model_description"
                                v-model="form.model_description"
                                rows="3"
                                placeholder="Describe el modelo 3D..."
                                class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            ></textarea>

                            <p
                                v-if="form.errors.model_description"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.model_description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Configuración -->
                <div class="space-y-4">
                    <h2 class="text-base font-semibold">
                        Configuración
                    </h2>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-sidebar-border p-4 transition hover:bg-accent/30"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-sidebar-border"
                        />

                        <span>
                            <span class="block text-sm font-medium">
                                Tarjeta activa
                            </span>

                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                            >
                                La tarjeta estará disponible en el sistema.
                            </span>
                        </span>
                    </label>

                    <p
                        v-if="form.errors.is_active"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.is_active }}
                    </p>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="admin.cards.index().url"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Guardar cambios'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>