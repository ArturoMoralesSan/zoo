<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import type { Component } from 'vue';

withDefaults(defineProps<{
    title: string;
    description?: string;
    section?: string;
    icon?: Component;
    actionIcon?: Component;
    actionLabel?: string;
    actionHref?: string;
}>(), {
    description: '',
    section: '',
    actionIcon: () => Plus,
    actionLabel: '',
    actionHref: '',
});

const emit = defineEmits<{
    action: [];
}>();
</script>

<template>
    <header
        class="rounded-xl border border-[#d7e2da] bg-white px-4 py-4 sm:px-5 sm:py-4"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <!-- Título, icono y descripción -->
            <div class="flex min-w-0 items-start gap-3">
                <div
                    v-if="icon"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187642]"
                >
                    <component :is="icon" :size="21" :stroke-width="1.8" />
                </div>

                <div class="min-w-0">
                    <h1
                        class="text-xl font-bold leading-tight tracking-tight text-[#17452d] sm:text-[21px]"
                    >
                        {{ title }}
                    </h1>

                    <p
                        v-if="description"
                        class="mt-1 text-xs leading-5 text-[#64756a] sm:text-[13px]"
                    >
                        {{ description }}
                    </p>

                    <slot name="details" />
                </div>
            </div>

            <!-- Acciones -->
            <div
                v-if="actionHref || actionLabel || $slots.actions"
                class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center sm:justify-end"
            >
                <slot name="actions" />

                <Link
                    v-if="actionHref && actionLabel"
                    :href="actionHref"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border-b-[3px] border-b-[#092f1a] bg-[#187642] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#126236] focus:outline-none focus:ring-2 focus:ring-[#187642]/20 sm:text-[13px]"
                >
                    <component :is="actionIcon" :size="16" />
                    <span>{{ actionLabel }}</span>
                </Link>

                <button
                    v-else-if="actionLabel"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border-b-[3px] border-b-[#092f1a] bg-[#187642] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#126236] focus:outline-none focus:ring-2 focus:ring-[#187642]/20 sm:text-[13px]"
                    @click="emit('action')"
                >
                    <component :is="actionIcon" :size="16" />
                    <span>{{ actionLabel }}</span>
                </button>
            </div>
        </div>

        <slot />
    </header>
</template>