
<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    UserRound,
    Mail,
    LockKeyhole,
} from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crear una cuenta',
        description: 'Ingresa tus datos para crear tu cuenta',
    },
});
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">

            <!-- Nombre -->
            <div class="grid gap-2">
                <Label
                    for="name"
                    class="font-medium text-[#023f1d]"
                >
                    Nombre completo
                </Label>

                <div class="relative">
                    <UserRound
                        class="pointer-events-none absolute left-3.5 top-1/2 z-10 h-[18px] w-[18px] -translate-y-1/2 text-[#668b75]"
                        :stroke-width="1.8"
                    />

                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Nombre completo"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.name" />
            </div>

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
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="correo@ejemplo.com"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.email" />
            </div>

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
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Contraseña"
                        :passwordrules="passwordRules"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.password" />
            </div>

            <!-- Confirmar contraseña -->
            <div class="grid gap-2">
                <Label
                    for="password_confirmation"
                    class="font-medium text-[#023f1d]"
                >
                    Confirmar contraseña
                </Label>

                <div class="relative">
                    <LockKeyhole
                        class="pointer-events-none absolute left-3.5 top-1/2 z-10 h-[18px] w-[18px] -translate-y-1/2 text-[#668b75]"
                        :stroke-width="1.8"
                    />

                    <PasswordInput
                        id="password_confirmation"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirmar contraseña"
                        :passwordrules="passwordRules"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.password_confirmation" />
            </div>

            <!-- Botón de registro -->
            <Button
                type="submit"
                class="mt-2 h-12 w-full border-b-2 border-b-[#083a1c] bg-[#06733a] text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#055d30] focus-visible:ring-[#06733a]/30"
                :tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />

                <span>
                    {{ processing ? 'Creando cuenta...' : 'Crear cuenta' }}
                </span>
            </Button>
        </div>

        <!-- Enlace para iniciar sesión -->
        <div class="text-center text-sm text-[#668071]">
            ¿Ya tienes una cuenta?

            <TextLink
                :href="login()"
                class="font-semibold text-[#06733a] hover:text-[#055d30]"
                :tabindex="6"
            >
                Iniciar sesión
            </TextLink>
        </div>
    </Form>
</template>
