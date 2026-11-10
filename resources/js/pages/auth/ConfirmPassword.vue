<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { LockKeyhole } from 'lucide-vue-next';

import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';

import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirmar contraseña',
        description:
            'Esta es un área segura de la aplicación. Confirma tu contraseña antes de continuar.',
    },
});
</script>

<template>
    <Head title="Confirmar contraseña" />

    <!-- Confirmación mediante Passkey -->
    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirmar con clave de acceso"
        loading-label="Confirmando..."
        separator="O confirmar con contraseña"
    />

    <!-- Confirmación mediante contraseña -->
    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">

            <!-- Contraseña -->
            <div class="grid gap-2">
                <Label
                    for="password"
                    class="font-medium text-[#023f1d]"
                >
                    Contraseña
                </Label>

                <div class="relative">
                    <LockKeyhole
                        class="pointer-events-none absolute left-3.5 top-1/2 z-10 h-[18px] w-[18px] -translate-y-1/2 text-[#668b75]"
                        :stroke-width="1.8"
                    />

                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        autofocus
                        placeholder="Contraseña"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.password" />
            </div>

            <!-- Botón de confirmación -->
            <Button
                type="submit"
                class="mt-2 h-12 w-full border-b-2 border-b-[#083a1c] bg-[#06733a] text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#055d30] focus-visible:ring-[#06733a]/30"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />

                <span>
                    {{ processing ? 'Confirmando...' : 'Confirmar contraseña' }}
                </span>
            </Button>
        </div>
    </Form>
</template>