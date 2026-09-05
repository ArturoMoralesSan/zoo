<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

/* @chisel-email-verification */
import { send } from '@/routes/verification';
/* @end-chisel-email-verification */

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configuración del perfil',
                href: edit(),
            },
        ],
    },
});

interface Profile {
    phone: string | null;
    birth_date: string | null;
    avatar: string | null;
    city: string | null;
    country: string | null;
}

const page = usePage();

const user = computed(() => page.props.auth.user);

const profile = computed(
    () => page.props.profile as Profile | null,
);

const avatarPreview = ref<string | null>(null);

const handleAvatarChange = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (!target.files || !target.files[0]) {
        avatarPreview.value = null;
        return;
    }

    // Liberar la URL anterior si existía
    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
    }

    avatarPreview.value = URL.createObjectURL(
        target.files[0],
    );
};
</script>

<template>
    <Head title="Configuración del perfil" />

    <h1 class="sr-only">
        Configuración del perfil
    </h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Perfil"
            description="Actualiza tu información personal"
        />

        <Form
            v-bind="ProfileController.update.form()"
            :options="{ forceFormData: true }"
            enctype="multipart/form-data"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <!-- Avatar -->
            <div class="grid gap-4">
                <Label for="avatar">
                    Foto de perfil
                </Label>

                <div class="flex items-center gap-5">
                    <!-- Vista previa -->
                    <div
                        class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted"
                    >
                        <!-- Nueva imagen seleccionada -->
                        <img
                            v-if="avatarPreview"
                            :src="avatarPreview"
                            alt="Vista previa del avatar"
                            class="h-full w-full object-cover"
                        />

                        <!-- Imagen actual -->
                        <img
                            v-else-if="profile?.avatar"
                            :src="`/storage/${profile.avatar}`"
                            alt="Avatar actual"
                            class="h-full w-full object-cover"
                        />

                        <!-- Sin imagen -->
                        <span
                            v-else
                            class="text-xs text-muted-foreground"
                        >
                            Sin foto
                        </span>
                    </div>

                    <!-- Selector de archivo -->
                    <div class="flex-1">
                        <Input
                            id="avatar"
                            type="file"
                            name="avatar"
                            accept="image/jpeg,image/png,image/webp"
                            @change="handleAvatarChange"
                        />

                        <p
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            JPG, PNG o WebP. Máximo 2 MB.
                        </p>

                        <InputError
                            class="mt-2"
                            :message="errors.avatar"
                        />
                    </div>
                </div>
            </div>

            <!-- Nombre -->
            <div class="grid gap-2">
                <Label for="name">
                    Nombre
                </Label>

                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Nombre completo"
                />

                <InputError
                    class="mt-2"
                    :message="errors.name"
                />
            </div>

            <!-- Correo -->
            <div class="grid gap-2">
                <Label for="email">
                    Correo electrónico
                </Label>

                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Correo electrónico"
                />

                <InputError
                    class="mt-2"
                    :message="errors.email"
                />
            </div>

            <!-- Teléfono -->
            <div class="grid gap-2">
                <Label for="phone">
                    Teléfono
                </Label>

                <Input
                    id="phone"
                    type="tel"
                    class="mt-1 block w-full"
                    name="phone"
                    :default-value="profile?.phone ?? ''"
                    autocomplete="tel"
                    placeholder="Número de teléfono"
                />

                <InputError
                    class="mt-2"
                    :message="errors.phone"
                />
            </div>

            <!-- Fecha de nacimiento -->
            <div class="grid gap-2">
                <Label for="birth_date">
                    Fecha de nacimiento
                </Label>

                <Input
                    id="birth_date"
                    type="date"
                    class="mt-1 block w-full"
                    name="birth_date"
                    :default-value="profile?.birth_date ?? ''"
                    autocomplete="bday"
                />

                <InputError
                    class="mt-2"
                    :message="errors.birth_date"
                />
            </div>

            <!-- Ciudad y país -->
            <div
                class="grid gap-6 md:grid-cols-2"
            >
                <!-- Ciudad -->
                <div class="grid gap-2">
                    <Label for="city">
                        Ciudad
                    </Label>

                    <Input
                        id="city"
                        type="text"
                        class="mt-1 block w-full"
                        name="city"
                        :default-value="profile?.city ?? ''"
                        autocomplete="address-level2"
                        placeholder="Ciudad"
                    />

                    <InputError
                        class="mt-2"
                        :message="errors.city"
                    />
                </div>

                <!-- País -->
                <div class="grid gap-2">
                    <Label for="country">
                        País
                    </Label>

                    <Input
                        id="country"
                        type="text"
                        class="mt-1 block w-full"
                        name="country"
                        :default-value="profile?.country ?? ''"
                        autocomplete="country-name"
                        placeholder="País"
                    />

                    <InputError
                        class="mt-2"
                        :message="errors.country"
                    />
                </div>
            </div>

            <!-- Verificación de correo -->
            <!-- @chisel-email-verification -->
            <div
                v-if="
                    page.props.mustVerifyEmail &&
                    !user.email_verified_at
                "
            >
                <p
                    class="-mt-4 text-sm text-muted-foreground"
                >
                    Tu dirección de correo electrónico
                    no está verificada.

                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Haz clic aquí para volver a enviar
                        el correo de verificación.
                    </Link>
                </p>

                <div
                    v-if="
                        page.props.status ===
                        'verification-link-sent'
                    "
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Se ha enviado un nuevo enlace de
                    verificación a tu dirección de correo
                    electrónico.
                </div>
            </div>
            <!-- @end-chisel-email-verification -->

            <!-- Guardar -->
            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{
                        processing
                            ? 'Guardando...'
                            : 'Guardar'
                    }}
                </Button>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>