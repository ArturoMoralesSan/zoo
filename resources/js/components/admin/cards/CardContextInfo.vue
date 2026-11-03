<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next';

/*
 * Aviso de la tarjeta a la que pertenece lo que se crea o edita
 * (preguntas del quiz, códigos QR): "Nombre · Especie — mensaje".
 */
defineProps<{
    card: {
        name: string;
        species?: { common_name?: string | null } | null;
    };
    message: string;
}>();
</script>

<template>
    <div class="form-info">
        <div class="form-info-icon">
            <CheckCircle2 class="h-5 w-5" />
        </div>

        <p class="form-info-text">
            <span class="font-semibold">{{ card.name }}</span>

            <span v-if="card.species?.common_name">
                · {{ card.species.common_name }}
            </span>

            — {{ message }}
        </p>
    </div>
</template>
