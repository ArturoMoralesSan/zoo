<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Box,
    Check,
    ChevronDown,
    FileBox,
    FileImage,
    Info,
    Layers3,
    Save,
    Sparkles,
    Tag,
    Upload,
    X,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

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
</script>

<template>
    <Head title="Nueva tarjeta" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Tag class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">
                            Nueva tarjeta
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Crea una nueva tarjeta coleccionable para una especie.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.cards.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <X class="h-4 w-4" />
                    Cancelar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-8"
                @submit.prevent="submit"
            >
                <!-- Información general -->
                <section class="space-y-6">
                    <div
                        class="flex items-start gap-3 border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Info class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold">
                                Información general
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Define los datos principales de la tarjeta.
                            </p>
                        </div>
                    </div>

                    <!-- Especie -->
                    <div class="space-y-2">
                        <label
                            for="species_id"
                            class="text-sm font-medium"
                        >
                            Especie
                        </label>

                        <div class="relative">
                            <select
                                id="species_id"
                                v-model="form.species_id"
                                class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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
                                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                        </div>

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

                            <div class="relative">
                                <select
                                    id="rarity"
                                    v-model="form.rarity"
                                    class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                                <ChevronDown
                                    class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>

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

                            <p class="text-xs text-muted-foreground">
                                Puedes dejarlo vacío si pertenece a la edición general.
                            </p>

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
                                Orden de aparición
                            </label>

                            <input
                                id="sort_order"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p class="text-xs text-muted-foreground">
                                Define el orden en que aparecerá la tarjeta.
                            </p>

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
                        />

                        <p
                            v-if="form.errors.description"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </section>

                <!-- Imagen -->
                <section class="space-y-5">
                    <div
                        class="flex items-start gap-3 border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <FileImage class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold">
                                Imagen de la tarjeta
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Sube la imagen que utilizará la tarjeta coleccionable.
                            </p>
                        </div>
                    </div>

                    <label
                        for="card_image"
                        class="group flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-sidebar-border bg-muted/20 px-6 py-10 text-center transition hover:border-primary/50 hover:bg-primary/5"
                    >
                        <div
                            class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-background text-muted-foreground shadow-sm transition group-hover:text-primary"
                        >
                            <Upload class="h-5 w-5" />
                        </div>

                        <span class="text-sm font-medium">
                            {{ form.card_image?.name || 'Selecciona una imagen' }}
                        </span>

                        <span class="mt-1 text-xs text-muted-foreground">
                            JPG, JPEG, PNG o WEBP · Máximo 5 MB
                        </span>

                        <input
                            id="card_image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="handleCardImage"
                        />
                    </label>

                    <p
                        v-if="form.errors.card_image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.card_image }}
                    </p>
                </section>

                <!-- Modelo 3D -->
                <section
                    class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div
                        class="mb-6 flex items-start gap-3 border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Box class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold">
                                Modelo 3D
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Configura el modelo 3D asociado a esta tarjeta.
                            </p>
                        </div>
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

                                <div class="relative">
                                    <select
                                        id="model_format"
                                        v-model="form.model_format"
                                        class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                </div>

                                <p
                                    v-if="form.errors.model_format"
                                    class="text-sm text-red-500"
                                >
                                    {{ form.errors.model_format }}
                                </p>
                            </div>
                        </div>

                        <!-- Archivo 3D -->
                        <div class="space-y-2">
                            <label
                                for="model_file"
                                class="text-sm font-medium"
                            >
                                Archivo 3D
                            </label>

                            <label
                                for="model_file"
                                class="group flex cursor-pointer items-center gap-4 rounded-xl border border-dashed border-sidebar-border bg-muted/20 p-5 transition hover:border-primary/50 hover:bg-primary/5"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-background text-muted-foreground shadow-sm transition group-hover:text-primary"
                                >
                                    <FileBox class="h-5 w-5" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ form.model_file?.name || 'Seleccionar archivo 3D' }}
                                    </p>

                                    <p class="mt-1 text-xs text-muted-foreground">
                                        GLB o GLTF · Máximo 50 MB
                                    </p>
                                </div>

                                <div
                                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium"
                                >
                                    <Upload class="h-3.5 w-3.5" />
                                    Examinar
                                </div>

                                <input
                                    id="model_file"
                                    type="file"
                                    accept=".glb,.gltf"
                                    class="hidden"
                                    @change="handleModelFile"
                                />
                            </label>

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

                        <!-- Descripción del modelo -->
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
                            />

                            <p
                                v-if="form.errors.model_description"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.model_description }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Configuración -->
                <section class="space-y-4">
                    <div
                        class="flex items-start gap-3 border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Layers3 class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold">
                                Configuración
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Controla la disponibilidad de la tarjeta.
                            </p>
                        </div>
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-4 rounded-xl border border-sidebar-border p-4 transition hover:bg-accent/30"
                    >
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border"
                            :class="
                                form.is_active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-sidebar-border'
                            "
                        >
                            <Check
                                v-if="form.is_active"
                                class="h-3.5 w-3.5"
                            />

                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="sr-only"
                            />
                        </div>

                        <span>
                            <span class="block text-sm font-medium">
                                Tarjeta activa
                            </span>

                            <span class="mt-1 block text-xs text-muted-foreground">
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
                </section>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="admin.cards.index().url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <X class="h-4 w-4" />
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Save
                            v-if="!form.processing"
                            class="h-4 w-4"
                        />

                        <Sparkles
                            v-else
                            class="h-4 w-4 animate-pulse"
                        />

                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Guardar tarjeta'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>