<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name;
</script>

<template>
    <div class="flex w-full items-center justify-center">
        <img
            src="/images/sonrie-corriendo.png"
            alt="Zoo"
            class="h-auto w-[175px] object-contain"
        />
    </div>
</template>