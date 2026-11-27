<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/*
 * Aviso junto al interruptor "Activa" de una tarjeta o especie con archivos
 * sin licencia. Activarla se permite, pero la app muestra esos archivos sin
 * sus créditos.
 */
defineProps<{
    // "la ilustración y el modelo 3D", "2 imágenes"…
    missing: string;
    kind: 'tarjeta' | 'especie';
    // Sección Créditos; sin ella (al crear) se explica que se abrirá al guardar.
    href?: string | null;
}>();
</script>

<template>
    <Alert class="mt-3 border-amber-300 bg-amber-50 text-amber-900">
        <AlertTriangle />
        <AlertTitle>Activa sin licencia completa</AlertTitle>
        <AlertDescription class="text-amber-800">
            <p>
                Falta la licencia de {{ missing }}. Si la {{ kind }} está
                activa, la app la muestra sin esos créditos.
            </p>
            <p>
                <Link
                    v-if="href"
                    :href="href"
                    class="font-semibold text-amber-900 underline"
                >
                    Registrar los créditos
                </Link>
                <template v-else>
                    Al guardar se abrirá la sección Créditos para registrarlos.
                </template>
            </p>
        </AlertDescription>
    </Alert>
</template>
