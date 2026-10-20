<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    KeyRound,
    LockKeyhole,
    Save,
    ShieldCheck,
} from 'lucide-vue-next';

import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import PasswordInput from '@/components/PasswordInput.vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import PageHeader from '@/components/admin/PageHeader.vue';

import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración de seguridad',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Configuración de seguridad" />

    <h1 class="sr-only">Configuración de seguridad</h1>

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Configuración de seguridad"
            description="Protege tu cuenta y administra tus métodos de autenticación."
            section="Cuenta"
            :icon="ShieldCheck"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="flex flex-col gap-5"
            v-slot="{ errors, processing }"
        >
            <AdminCard
                title="Actualizar contraseña"
                description="Utiliza una contraseña larga y única para mantener tu cuenta segura."
                :icon="LockKeyhole"
            >
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="current_password" class="form-label">
                            Contraseña actual
                            <span class="form-required">*</span>
                        </label>

                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            class="form-input"
                            autocomplete="current-password"
                            placeholder="Ingresa tu contraseña actual"
                        />

                        <InputError
                            :message="errors.current_password"
                            class="form-error"
                        />

                        <p
                            v-if="!errors.current_password"
                            class="form-help"
                        >
                            Confirma tu identidad antes de cambiar la contraseña.
                        </p>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="password" class="form-label">
                            Nueva contraseña
                            <span class="form-required">*</span>
                        </label>

                        <PasswordInput
                            id="password"
                            name="password"
                            class="form-input"
                            autocomplete="new-password"
                            placeholder="Ingresa una nueva contraseña"
                            :passwordrules="props.passwordRules"
                        />

                        <InputError
                            :message="errors.password"
                            class="form-error"
                        />

                        <p v-if="!errors.password" class="form-help">
                            Elige una contraseña difícil de adivinar y evita
                            reutilizar contraseñas anteriores.
                        </p>
                    </div>

                    <div class="form-field form-field-full">
                        <label
                            for="password_confirmation"
                            class="form-label"
                        >
                            Confirmar contraseña
                            <span class="form-required">*</span>
                        </label>

                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            class="form-input"
                            autocomplete="new-password"
                            placeholder="Confirma tu nueva contraseña"
                            :passwordrules="props.passwordRules"
                        />

                        <InputError
                            :message="errors.password_confirmation"
                            class="form-error"
                        />

                        <p
                            v-if="!errors.password_confirmation"
                            class="form-help"
                        >
                            Escribe nuevamente la nueva contraseña para
                            verificar que coincida.
                        </p>
                    </div>
                </div>

                <div class="form-info mt-5">
                    <ShieldCheck
                        class="form-info-icon"
                        :size="20"
                    />

                    <p class="form-info-text">
                        Mantén tu contraseña privada y no la compartas con
                        otras personas.
                    </p>
                </div>

                <div
                    class="mt-5 flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
                >
                    <AdminButton
                        type="submit"
                        variant="primary"
                        :icon="Save"
                        :loading="processing"
                        loading-text="Guardando..."
                        data-test="update-password-button"
                    >
                        Actualizar contraseña
                    </AdminButton>
                </div>
            </AdminCard>
        </Form>

        <AdminCard
            title="Autenticación de dos factores"
            description="Añade una capa adicional de protección al iniciar sesión."
            :icon="ShieldCheck"
        >
            <ManageTwoFactor
                :canManageTwoFactor="canManageTwoFactor"
                :requiresConfirmation="requiresConfirmation"
                :twoFactorEnabled="twoFactorEnabled"
            />
        </AdminCard>

        <AdminCard
            title="Llaves de acceso"
            description="Administra tus passkeys para iniciar sesión con un método de autenticación compatible."
            :icon="KeyRound"
        >
            <ManagePasskeys
                :canManagePasskeys="canManagePasskeys"
                :passkeys="passkeys"
            />
        </AdminCard>
    </div>
</template>