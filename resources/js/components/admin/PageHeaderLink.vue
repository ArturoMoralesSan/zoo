<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

/*
 * Acción secundaria del encabezado de página (slot "actions" de PageHeader),
 * del mismo tamaño que su botón principal.
 */
defineProps<{
    href: string;
    label: string;
    icon?: Component;
}>();
</script>

<template>
    <Link
        :href="href"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#d7e2da] border-b-[3px] border-b-[#c8d8ce] bg-white px-3.5 py-2 text-xs font-semibold text-[#244334] transition hover:bg-[#f2f7f4] focus:outline-none focus:ring-2 focus:ring-[#187642]/20 sm:text-[13px]"
    >
        <component :is="icon" v-if="icon" :size="16" />
        <span>{{ label }}</span>
    </Link>
</template>
