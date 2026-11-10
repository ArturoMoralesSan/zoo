<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recuperar contraseña',
        description:
            'Ingresa tu correo electrónico para recibir un enlace para restablecer tu contraseña',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar contraseña" />

    <!-- Mensaje de estado -->
    <div
        v-if="status"
        class="mb-5 rounded-lg border border-[#aec6bb] bg-[#f3f8f4] px-4 py-3 text-center text-sm font-medium text-[#06733a]"
    >
        {{ status }}
    </div>

    <div class="flex flex-col gap-6">
        <Form
            v-bind="email.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <!-- Correo electrónico -->
            <div class="grid gap-2">
                <Label
                    for="email"
                    class="font-medium text-[#023f1d]"
                >
                    Correo electrónico
                </Label>

                <div class="relative">
                    <Mail
                        class="pointer-events-none absolute left-3.5 top-1/2 z-10 h-[18px] w-[18px] -translate-y-1/2 text-[#668b75]"
                        :stroke-width="1.8"
                    />

                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        autofocus
                        required
                        placeholder="correo@ejemplo.com"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.email" />
            </div>

            <!-- Botón para enviar enlace -->
            <Button
                type="submit"
                class="mt-2 h-12 w-full border-b-2 border-b-[#083a1c] bg-[#06733a] text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#055d30] focus-visible:ring-[#06733a]/30"
                :disabled="processing"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />

                <span>
                    {{
                        processing
                            ? 'Enviando enlace...'
                            : 'Enviar enlace para restablecer contraseña'
                    }}
                </span>
            </Button>
        </Form>

        <!-- Regresar al inicio de sesión -->
        <div class="text-center text-sm text-[#668071]">
            <span>O regresar a </span>

            <TextLink
                :href="login()"
                class="font-semibold text-[#06733a] hover:text-[#055d30]"
            >
                iniciar sesión
            </TextLink>
        </div>
    </div>
</template>