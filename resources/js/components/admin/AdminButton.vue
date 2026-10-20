<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
        variant?: 'primary' | 'secondary' | 'danger';
        icon?: Component;
        disabled?: boolean;
        loading?: boolean;
        loadingText?: string;
        block?: boolean;
    }>(),
    {
        type: 'button',
        variant: 'primary',
        disabled: false,
        loading: false,
        loadingText: '',
        block: false,
    },
);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :class="[
            'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border-b-[3px] px-4 py-2.5 text-sm font-semibold transition-colors duration-200',
            'focus:outline-none focus:ring-2 focus:ring-offset-2',
            'disabled:cursor-not-allowed disabled:opacity-50',
            block ? 'w-full' : '',
            variant === 'primary'
                ? 'border-b-[#092f1a] bg-[#187642] text-white hover:bg-[#126236] focus:ring-[#187642]/20'
                : '',
            variant === 'secondary'
                ? 'border-b-[#c8d8ce] bg-white text-[#244334] hover:bg-[#f2f7f4] focus:ring-[#187642]/20'
                : '',
            variant === 'danger'
                ? 'border-b-[#7f1d1d] bg-[#b91c1c] text-white hover:bg-[#991b1b] focus:ring-red-500/20'
                : '',
        ]"
    >
        <!-- Indicador de carga -->
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />

            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
            />
        </svg>

        <!-- Icono normal -->
        <component
            :is="icon"
            v-else-if="icon"
            class="h-4 w-4 shrink-0"
            :size="16"
            :stroke-width="2"
            aria-hidden="true"
        />

        <!-- Texto -->
        <span>
            {{ loading && loadingText ? loadingText : $slots.default ? '' : '' }}
            <slot v-if="!loading || !loadingText" />
        </span>
    </button>
</template>