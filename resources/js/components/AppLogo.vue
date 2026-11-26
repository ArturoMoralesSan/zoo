<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name;
</script>

<template>
    <div class="flex w-full items-center justify-center">
        <!-- El logo es blanco con fondo transparente; invert lo vuelve negro para el sidebar blanco. -->
        <img
            src="/images/zooapp-logo.png"
            alt="ZooApp"
            class="h-auto w-[140px] object-contain invert"
        />
    </div>
</template>