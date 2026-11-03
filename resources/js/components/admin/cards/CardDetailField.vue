<script setup lang="ts">
/*
 * Dato de solo lectura en el detalle de la tarjeta: etiqueta y contenido.
 */
defineProps<{
    label: string;
}>();
</script>

<template>
    <div class="rounded-lg bg-[#F6F9F6] p-3">
        <p class="text-xs text-[#718477]">{{ label }}</p>

        <div class="mt-1 text-sm font-semibold text-[#244334]">
            <slot />
        </div>
    </div>
</template>
