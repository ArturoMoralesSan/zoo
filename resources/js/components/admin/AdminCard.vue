
<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: Component;
        padding?: boolean;
    }>(),
    {
        title: '',
        description: '',
        padding: true,
    },
);
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-[#DCE7DF] bg-white"
    >
        <!-- Encabezado opcional -->
        <div
            v-if="title || description || icon || $slots.header"
            class="border-b border-[#E5ECE7] px-5 py-4 sm:px-6"
        >
            <slot name="header">
                <div class="flex items-start gap-3">
                    <div
                        v-if="icon"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
                    >
                        <component
                            :is="icon"
                            :size="20"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="min-w-0">
                        <h2
                            v-if="title"
                            class="text-base font-semibold text-[#244334]"
                        >
                            {{ title }}
                        </h2>

                        <p
                            v-if="description"
                            class="mt-1 text-sm leading-5 text-[#718477]"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>
            </slot>
        </div>

        <!-- Contenido -->
        <div :class="padding ? 'p-5 sm:p-6' : ''">
            <slot />
        </div>

        <!-- Pie opcional: solo aparece si se proporciona #footer -->
        <div
            v-if="$slots.footer"
            class="flex flex-col gap-3 border-t border-[#DCE7DF] bg-[#FAFCFA] px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6"
        >
            <slot name="footer" />
        </div>
    </section>
</template>
