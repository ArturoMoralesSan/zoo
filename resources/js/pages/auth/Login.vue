
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Mail, LockKeyhole } from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Inicia sesión',
        description: 'Ingresa con tu cuenta de administrador',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <!-- Mensaje de estado -->
    <div
        v-if="status"
        class="mb-5 rounded-lg border border-[#aec6bb] bg-[#f3f8f4] px-4 py-3 text-center text-sm font-medium text-[#023f1d]"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">

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
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="correo@ejemplo.com"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.email" />
            </div>

            <!-- Contraseña -->
            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-2">
                    <Label
                        for="password"
                        class="font-medium text-[#023f1d]"
                    >
                        Contraseña
                    </Label>

                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm font-medium text-[#06733a] hover:text-[#055d30]"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>

                <div class="relative">
                    <LockKeyhole
                        class="pointer-events-none absolute left-3.5 top-1/2 z-10 h-[18px] w-[18px] -translate-y-1/2 text-[#668b75]"
                        :stroke-width="1.8"
                    />

                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Contraseña"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.password" />
            </div>

            <!-- Recordarme -->
            <div class="flex items-center">
                <Label
                    for="remember"
                    class="flex cursor-pointer items-center gap-3 text-sm font-normal text-[#023f1d]"
                >
                    <Checkbox
                        id="remember"
                        name="remember"
                        :tabindex="3"
                        class="border-[#aec6bb] data-[state=checked]:border-[#06733a] data-[state=checked]:bg-[#06733a] data-[state=checked]:text-white"
                    />

                    <span>Recordarme</span>
                </Label>
            </div>

            <!-- Botón de acceso -->
            <Button
                type="submit"
                class="mt-2 h-12 w-full border-b-2 border-b-[#083a1c] bg-[#06733a] text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#055d30] focus-visible:ring-[#06733a]/30"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />

                <span>
                    {{ processing ? 'Ingresando...' : 'Iniciar sesión' }}
                </span>
            </Button>
        </div>

        <!-- Registro -->
        <div class="text-center text-sm text-[#668071]">
            ¿No tienes una cuenta?

            <TextLink
                :href="register()"
                :tabindex="5"
                class="font-semibold text-[#06733a] hover:text-[#055d30]"
            >
                Regístrate
            </TextLink>
        </div>
    </Form>
</template>
