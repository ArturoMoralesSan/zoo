<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        href: string;
        label?: string;
        icon?: Component;
        block?: boolean;
    }>(),
    {
        label: 'Cancelar',
        block: false,
    },
);
</script>

<template>
    <Link
        :href="href"
        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#DCE7DF] border-b-[3px] border-b-[#c8d8ce] bg-white px-4 py-2.5 text-sm font-semibold text-[#244334] transition-colors duration-200 hover:bg-[#f2f7f4] focus:outline-none focus:ring-2 focus:ring-[#187642]/20 focus:ring-offset-2"
        :class="block ? 'w-full' : ''"
    >
        <component
            :is="icon"
            v-if="icon"
            class="h-4 w-4 shrink-0"
            :size="16"
            :stroke-width="2"
            aria-hidden="true"
        />

        <slot>{{ label }}</slot>
    </Link>
</template>