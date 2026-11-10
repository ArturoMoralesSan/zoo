<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Mail,
    LockKeyhole,
} from 'lucide-vue-next';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Restablecer contraseña',
        description: 'Ingresa tu nueva contraseña a continuación',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Restablecer contraseña" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({
            ...data,
            token,
            email: inputEmail,
        })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">

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
                        v-model="inputEmail"
                        readonly
                        class="h-12 border-[#aec6bb] bg-[#f3f8f4] pl-11 text-[#668071] focus-visible:border-[#aec6bb] focus-visible:ring-0"
                    />
                </div>

                <InputError :message="errors.email" />
            </div>

            <!-- Nueva contraseña -->
            <div class="grid gap-2">
                <Label
                    for="password"
                    class="font-medium text-[#023f1d]"
                >
                    Nueva contraseña
                </Label>

                <div class="relative">
                    <LockKeyhole
                        class="pointer-events-none absolute left-3.5 top-1/2 z-10 h-[18px] w-[18px] -translate-y-1/2 text-[#668b75]"
                        :stroke-width="1.8"
                    />

                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        autofocus
                        placeholder="Nueva contraseña"
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
                        name="password_confirmation"
                        autocomplete="new-password"
                        placeholder="Confirmar contraseña"
                        :passwordrules="passwordRules"
                        class="h-12 border-[#aec6bb] bg-white pl-11 text-[#023f1d] placeholder:text-[#8da698] focus-visible:border-[#06733a] focus-visible:ring-[#06733a]/15"
                    />
                </div>

                <InputError :message="errors.password_confirmation" />
            </div>

            <!-- Botón de restablecimiento -->
            <Button
                type="submit"
                class="mt-2 h-12 w-full border-b-2 border-b-[#083a1c] bg-[#06733a] text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#055d30] focus-visible:ring-[#06733a]/30"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />

                <span>
                    {{ processing ? 'Restableciendo...' : 'Restablecer contraseña' }}
                </span>
            </Button>
        </div>
    </Form>
</template>