<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    CircleUser,
    GripVertical,
    ImagePlus,
    LoaderCircle,
    Star,
    Trash2,
    Undo2,
    X,
} from 'lucide-vue-next';
import { computed, markRaw, onBeforeUnmount, ref, useId, watch } from 'vue';

import { cn } from '@/lib/utils';

// Galería de una especie en una sola rejilla: fotos guardadas y nuevas
// juntas, en el orden final. Nada se aplica hasta guardar el formulario:
// las nuevas viajan en `files`, el orden y el texto alternativo en `layout`
// y las eliminadas en `deletedIds` (ver SpeciesController::syncGallery).

export interface SavedGalleryImage {
    id: number;
    url: string;
    alt_text: string | null;
}

export interface GalleryLayoutItem {
    id?: number;
    file_index?: number;
    alt_text: string;
}

interface Tile {
    key: string;
    id?: number;
    file?: File;
    url: string;
    alt: string;
    deleted: boolean;
}

const props = withDefaults(
    defineProps<{
        existing?: SavedGalleryImage[];
        errors: Partial<Record<string, string>>;
    }>(),
    { existing: () => [] },
);

const files = defineModel<File[]>('files', { required: true });
const layout = defineModel<GalleryLayoutItem[]>('layout', { required: true });
const deletedIds = defineModel<number[]>('deletedIds', { default: () => [] });

const emit = defineEmits<{
    'use-as-main': [file: File];
    'use-as-thumbnail': [file: File];
}>();

// Mismos límites que StoreSpeciesRequest/UpdateSpeciesRequest.
const MAX_BYTES = 5 * 1024 * 1024;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const inputId = useId();
let sequence = 0;

const tiles = ref<Tile[]>(
    props.existing.map((image) => ({
        key: `saved-${image.id}`,
        id: image.id,
        url: image.url,
        alt: image.alt_text ?? '',
        deleted: false,
    })),
);

const liveTiles = computed(() => tiles.value.filter((tile) => !tile.deleted));

const fileIndexByKey = computed(() => {
    const map = new Map<string, number>();

    liveTiles.value
        .filter((tile) => tile.file)
        .forEach((tile, index) => map.set(tile.key, index));

    return map;
});

watch(
    tiles,
    () => {
        files.value = liveTiles.value
            .filter((tile) => tile.file)
            .map((tile) => tile.file as File);

        layout.value = liveTiles.value.map((tile) =>
            tile.file
                ? {
                      file_index: fileIndexByKey.value.get(tile.key),
                      alt_text: tile.alt.trim(),
                  }
                : { id: tile.id, alt_text: tile.alt.trim() },
        );

        deletedIds.value = tiles.value
            .filter((tile) => tile.deleted && tile.id !== undefined)
            .map((tile) => tile.id as number);
    },
    { deep: true, immediate: true },
);

const summary = computed(() => {
    const total = liveTiles.value.length;
    const added = fileIndexByKey.value.size;
    const removed = deletedIds.value.length;
    const parts = [`${total} ${total === 1 ? 'foto' : 'fotos'}`];

    if (added) {
parts.push(`${added} ${added === 1 ? 'nueva' : 'nuevas'}`);
}

    if (removed) {
parts.push(`${removed} por eliminar`);
}

    return parts.join(' · ');
});

const position = (tile: Tile) => liveTiles.value.indexOf(tile) + 1;

const tileError = (tile: Tile): string | undefined => {
    const fileIndex = fileIndexByKey.value.get(tile.key);
    const fileError =
        fileIndex === undefined
            ? undefined
            : props.errors[`gallery_images.${fileIndex}`];

    return fileError ?? props.errors[`gallery.${position(tile) - 1}.alt_text`];
};

/*
 * Agregar archivos. Se suman a los que ya hay; los inválidos se avisan
 * aquí en lugar de esperar el error del servidor.
 */
const rejected = ref<string[]>([]);

const addFiles = (list: FileList | null | undefined) => {
    rejected.value = [];

    for (const file of Array.from(list ?? [])) {
        if (!ACCEPTED_TYPES.includes(file.type)) {
            rejected.value.push(`${file.name}: formato no permitido`);
            continue;
        }

        if (file.size > MAX_BYTES) {
            rejected.value.push(`${file.name}: pesa más de 5 MB`);
            continue;
        }

        // markRaw: FormData necesita el File real, no un proxy reactivo.
        tiles.value.push({
            key: `new-${++sequence}`,
            file: markRaw(file),
            url: URL.createObjectURL(file),
            alt: '',
            deleted: false,
        });
    }
};

const onInputChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    addFiles(input.files);
    // Permite volver a elegir el mismo archivo.
    input.value = '';
};

const remove = (tile: Tile) => {
    if (tile.file) {
        URL.revokeObjectURL(tile.url);
        tiles.value = tiles.value.filter((item) => item !== tile);
    } else {
        tile.deleted = true;
    }
};

/*
 * Orden: arrastrar y soltar, o las flechas (teclado y pantallas táctiles).
 */
const draggingKey = ref<string | null>(null);
const overKey = ref<string | null>(null);
const filesOver = ref(false);

const moveTo = (tile: Tile, targetIndex: number) => {
    const from = tiles.value.indexOf(tile);

    if (from === -1 || targetIndex < 0 || targetIndex >= tiles.value.length) {
return;
}

    const next = [...tiles.value];
    next.splice(from, 1);
    next.splice(targetIndex, 0, tile);
    tiles.value = next;
};

const shift = (tile: Tile, direction: -1 | 1) => {
    moveTo(tile, tiles.value.indexOf(tile) + direction);
};

const onTileDragStart = (tile: Tile, event: DragEvent) => {
    draggingKey.value = tile.key;
    event.dataTransfer?.setData('text/plain', tile.key);

    if (event.dataTransfer) {
event.dataTransfer.effectAllowed = 'move';
}
};

const onTileDragOver = (tile: Tile) => {
    if (draggingKey.value) {
overKey.value = tile.key;
}
};

const onTileDrop = (tile: Tile) => {
    const dragged = tiles.value.find((item) => item.key === draggingKey.value);

    if (dragged && dragged !== tile) {
moveTo(dragged, tiles.value.indexOf(tile));
}

    onDragEnd();
};

const onDragEnd = () => {
    draggingKey.value = null;
    overKey.value = null;
};

const isFileDrag = (event: DragEvent) =>
    event.dataTransfer?.types.includes('Files') ?? false;

const onAreaDragOver = (event: DragEvent) => {
    if (isFileDrag(event)) {
filesOver.value = true;
}
};

const onAreaDrop = (event: DragEvent) => {
    filesOver.value = false;

    if (isFileDrag(event)) {
addFiles(event.dataTransfer?.files);
}
};

/*
 * Usar una foto como imagen principal o miniatura. Las guardadas se
 * descargan y se envían como archivo nuevo, igual que si se subieran.
 */
const busyKey = ref<string | null>(null);
const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | undefined;

const showNotice = (type: 'success' | 'error', text: string) => {
    notice.value = { type, text };
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => (notice.value = null), 4000);
};

const fileFor = async (tile: Tile): Promise<File> => {
    if (tile.file) {
return tile.file;
}

    const response = await fetch(tile.url);
    const blob = await response.blob();

    if (!response.ok || !ACCEPTED_TYPES.includes(blob.type)) {
        throw new Error('Imagen no disponible');
    }

    const name = tile.url.split('/').pop()?.split('?')[0] || 'imagen';

    return markRaw(new File([blob], name, { type: blob.type }));
};

const useAs = async (tile: Tile, target: 'main' | 'thumbnail') => {
    busyKey.value = `${tile.key}-${target}`;

    try {
        const file = await fileFor(tile);

        if (target === 'main') {
            emit('use-as-main', file);
            showNotice('success', 'Será la imagen principal al guardar.');
        } else {
            emit('use-as-thumbnail', file);
            showNotice('success', 'Será la miniatura al guardar.');
        }
    } catch {
        showNotice(
            'error',
            'No fue posible usar esa foto. Inténtalo de nuevo.',
        );
    } finally {
        busyKey.value = null;
    }
};

onBeforeUnmount(() => {
    clearTimeout(noticeTimer);
    tiles.value.forEach((tile) => tile.file && URL.revokeObjectURL(tile.url));
});

const iconButton =
    'flex h-8 w-8 items-center justify-center rounded-full bg-white/95 text-[#244334] shadow-sm transition hover:bg-white disabled:cursor-wait disabled:opacity-60';
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-[#244334]">Galería</h3>
                <p class="mt-1 text-xs text-[#718477]">
                    Arrastra las fotos o usa las flechas para ordenarlas; la app
                    las muestra en este orden. Si dejas vacío el texto
                    alternativo se usa el nombre común. Los cambios se aplican
                    al guardar.
                </p>
            </div>

            <p class="shrink-0 text-xs font-medium text-[#52685a]">
                {{ summary }}
            </p>
        </div>

        <p
            v-if="notice"
            role="status"
            :class="
                cn(
                    'rounded-lg border px-3 py-2 text-sm',
                    notice.type === 'success'
                        ? 'border-[#c8dccf] bg-[#EAF4ED] text-[#187744]'
                        : 'border-red-200 bg-red-50 text-red-700',
                )
            "
        >
            {{ notice.text }}
        </p>

        <div
            v-if="rejected.length"
            role="alert"
            class="flex items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
            <div>
                <p class="font-medium">
                    No se agregaron estos archivos (JPG, PNG o WEBP, máx. 5 MB):
                </p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="item in rejected" :key="item">{{ item }}</li>
                </ul>
            </div>

            <button
                type="button"
                aria-label="Cerrar aviso"
                class="shrink-0"
                @click="rejected = []"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <ul
            :class="
                cn(
                    'grid grid-cols-2 gap-3 rounded-xl p-1 transition sm:grid-cols-3 lg:grid-cols-4',
                    filesOver && 'bg-[#EAF4ED] ring-2 ring-[#187744]',
                )
            "
            @dragover.prevent="onAreaDragOver"
            @dragleave.self="filesOver = false"
            @drop.prevent="onAreaDrop"
        >
            <li
                v-for="tile in tiles"
                :key="tile.key"
                :draggable="!tile.deleted"
                :class="
                    cn(
                        'group flex flex-col overflow-hidden rounded-xl border bg-white transition',
                        tile.file ? 'border-[#187744]' : 'border-[#DCE7DF]',
                        tileError(tile) && 'border-red-400',
                        draggingKey === tile.key && 'opacity-40',
                        overKey === tile.key &&
                            draggingKey !== tile.key &&
                            'ring-2 ring-[#187744]',
                    )
                "
                @dragstart="onTileDragStart(tile, $event)"
                @dragover="onTileDragOver(tile)"
                @drop="onTileDrop(tile)"
                @dragend="onDragEnd"
            >
                <div class="relative aspect-square bg-[#F7FAF8]">
                    <img
                        :src="tile.url"
                        :alt="tile.alt || 'Foto de la galería'"
                        draggable="false"
                        :class="
                            cn(
                                'h-full w-full object-cover',
                                tile.deleted && 'opacity-30 grayscale',
                            )
                        "
                    />

                    <div
                        v-if="!tile.deleted"
                        class="absolute top-2 left-2 flex gap-1"
                    >
                        <span
                            class="rounded-md bg-white/95 px-1.5 py-0.5 text-xs font-semibold text-[#244334] shadow-sm"
                        >
                            {{ position(tile) }}
                        </span>
                        <span
                            v-if="tile.file"
                            class="rounded-md bg-[#187744] px-1.5 py-0.5 text-xs font-semibold text-white"
                        >
                            Nueva
                        </span>
                    </div>

                    <div
                        v-if="!tile.deleted"
                        class="absolute top-2 right-2 flex flex-col gap-1 transition sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100"
                    >
                        <button
                            type="button"
                            title="Usar como imagen principal"
                            aria-label="Usar como imagen principal"
                            :disabled="busyKey !== null"
                            :class="iconButton"
                            @click="useAs(tile, 'main')"
                        >
                            <LoaderCircle
                                v-if="busyKey === `${tile.key}-main`"
                                class="h-4 w-4 animate-spin"
                            />
                            <Star v-else class="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            title="Usar como miniatura"
                            aria-label="Usar como miniatura"
                            :disabled="busyKey !== null"
                            :class="iconButton"
                            @click="useAs(tile, 'thumbnail')"
                        >
                            <LoaderCircle
                                v-if="busyKey === `${tile.key}-thumbnail`"
                                class="h-4 w-4 animate-spin"
                            />
                            <CircleUser v-else class="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            :title="tile.file ? 'Quitar foto' : 'Eliminar foto'"
                            :aria-label="
                                tile.file ? 'Quitar foto' : 'Eliminar foto'
                            "
                            :class="
                                cn(iconButton, 'text-red-600 hover:bg-red-50')
                            "
                            @click="remove(tile)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div
                        v-if="tile.deleted"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-2 text-center"
                    >
                        <p class="text-xs font-semibold text-red-700">
                            Se eliminará al guardar
                        </p>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border border-[#c8dccf] bg-white px-3 py-1 text-xs font-medium text-[#244334] shadow-sm hover:border-[#187744]"
                            @click="tile.deleted = false"
                        >
                            <Undo2 class="h-3.5 w-3.5" />
                            Deshacer
                        </button>
                    </div>
                </div>

                <div v-if="!tile.deleted" class="space-y-2 p-2">
                    <input
                        v-model="tile.alt"
                        type="text"
                        maxlength="255"
                        placeholder="Texto alternativo"
                        :aria-label="`Texto alternativo de la foto ${position(tile)}`"
                        class="form-input h-8 px-2 text-xs"
                    />

                    <div class="flex items-center justify-between">
                        <span
                            class="flex cursor-grab items-center text-[#84958A]"
                            title="Arrastra para ordenar"
                        >
                            <GripVertical class="h-4 w-4" />
                        </span>

                        <div class="flex gap-1">
                            <button
                                type="button"
                                aria-label="Mover antes"
                                :disabled="tiles.indexOf(tile) === 0"
                                class="rounded-md p-1 text-[#52685a] hover:bg-[#EAF4ED] disabled:opacity-30"
                                @click="shift(tile, -1)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                aria-label="Mover después"
                                :disabled="
                                    tiles.indexOf(tile) === tiles.length - 1
                                "
                                class="rounded-md p-1 text-[#52685a] hover:bg-[#EAF4ED] disabled:opacity-30"
                                @click="shift(tile, 1)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <p v-if="tileError(tile)" class="text-xs text-red-600">
                        {{ tileError(tile) }}
                    </p>
                </div>
            </li>

            <li>
                <label
                    :for="inputId"
                    class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-[#c8dccf] bg-[#F7FAF8] p-3 text-center transition hover:border-[#187744] hover:bg-[#EAF4ED]"
                >
                    <ImagePlus class="h-7 w-7 text-[#187744]" />
                    <span class="text-sm font-semibold text-[#187744]"
                        >Agregar fotos</span
                    >
                    <span class="text-xs text-[#718477]"
                        >o arrástralas aquí</span
                    >
                    <span class="mt-1 text-[11px] text-[#84958A]"
                        >JPG, PNG o WEBP · máx. 5 MB</span
                    >
                </label>

                <input
                    :id="inputId"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp"
                    multiple
                    class="sr-only"
                    @change="onInputChange"
                />
            </li>
        </ul>

        <p v-if="errors.gallery_images" class="form-error">
            {{ errors.gallery_images }}
        </p>
        <p v-if="errors.gallery" class="form-error">{{ errors.gallery }}</p>
        <p v-if="errors.deleted_gallery_ids" class="form-error">
            {{ errors.deleted_gallery_ids }}
        </p>
    </div>
</template>
