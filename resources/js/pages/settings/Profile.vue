<script setup lang="ts">
import {
    Form,
    Head,
    Link,
    usePage,
} from '@inertiajs/vue3';

import {
    computed,
    onMounted,
    ref,
} from 'vue';

import QRCode from 'qrcode';

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

/*
|--------------------------------------------------------------------------
| Interfaces
|--------------------------------------------------------------------------
*/

interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    qr_token: string | null;
}

interface Profile {
    phone: string | null;
    birth_date: string | null;
    avatar: string | null;
    city: string | null;
    country: string | null;
}

interface Level {
    id: number;
    name: string;
    min_points: number;
    max_points: number | null;
}

interface Gamification {
    points: number;
    current_level: Level | null;
    next_level: Level | null;
    progress: number;
    points_to_next_level: number;
}

/*
|--------------------------------------------------------------------------
| Página
|--------------------------------------------------------------------------
*/

const page = usePage();

const user = computed(
    () => page.props.auth.user as User,
);

const profile = computed(
    () =>
        page.props.profile as
            | Profile
            | null,
);

const gamification = computed(
    () =>
        page.props.gamification as
            | Gamification
            | undefined,
);

/*
|--------------------------------------------------------------------------
| QR del usuario
|--------------------------------------------------------------------------
*/

const userQrCode = ref<string | null>(null);

const generateUserQrCode = async (): Promise<void> => {
    if (!user.value.qr_token) {
        console.warn(
            'El usuario no tiene qr_token.',
        );

        return;
    }

    try {
        userQrCode.value =
            await QRCode.toDataURL(
                user.value.qr_token,
                {
                    width: 400,
                    margin: 2,
                    errorCorrectionLevel: 'H',
                },
            );
    } catch (error) {
        console.error(
            'Error generando QR del usuario:',
            error,
        );
    }
};

onMounted(() => {
    generateUserQrCode();
});

/*
|--------------------------------------------------------------------------
| Avatar
|--------------------------------------------------------------------------
*/

const avatarPreview = ref<string | null>(
    null,
);

const handleAvatarChange = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    if (
        !target.files ||
        !target.files[0]
    ) {
        avatarPreview.value = null;
        return;
    }

    if (avatarPreview.value) {
        URL.revokeObjectURL(
            avatarPreview.value,
        );
    }

    avatarPreview.value =
        URL.createObjectURL(
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

        <!-- Encabezado -->

        <Heading
            variant="small"
            title="Perfil"
            description="Actualiza tu información personal"
        />

        <!-- Gamificación -->

        <div
            v-if="gamification"
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-6"
            >

                <!-- Encabezado -->

                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <p
                            class="text-sm text-muted-foreground"
                        >
                            Nivel actual
                        </p>

                        <h2
                            class="mt-1 text-2xl font-semibold"
                        >
                            {{
                                gamification
                                    .current_level
                                    ?.name ??
                                'Sin nivel'
                            }}
                        </h2>
                    </div>

                    <div
                        class="md:text-right"
                    >
                        <p
                            class="text-2xl font-semibold"
                        >
                            {{
                                gamification.points.toLocaleString(
                                    'es-MX',
                                )
                            }}
                        </p>

                        <p
                            class="text-sm text-muted-foreground"
                        >
                            puntos acumulados
                        </p>
                    </div>
                </div>

                <!-- Progreso -->

                <div>
                    <div
                        class="mb-2 flex items-center justify-between text-sm"
                    >
                        <span
                            class="font-medium"
                        >
                            Progreso
                        </span>

                        <span
                            class="text-muted-foreground"
                        >
                            {{
                                gamification.progress
                            }}%
                        </span>
                    </div>

                    <div
                        class="h-3 w-full overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-500"
                            :style="{
                                width: `${gamification.progress}%`,
                            }"
                        />
                    </div>
                </div>

                <!-- Información de niveles -->

                <div
                    class="grid gap-4 md:grid-cols-2"
                >

                    <!-- Nivel actual -->

                    <div
                        class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Nivel actual
                        </p>

                        <p
                            class="mt-1 font-semibold"
                        >
                            {{
                                gamification
                                    .current_level
                                    ?.name ??
                                'Sin nivel'
                            }}
                        </p>

                        <p
                            v-if="
                                gamification.current_level
                            "
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Desde
                            {{
                                gamification
                                    .current_level
                                    .min_points.toLocaleString(
                                        'es-MX',
                                    )
                            }}
                            puntos
                        </p>
                    </div>

                    <!-- Siguiente nivel -->

                    <div
                        class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Siguiente nivel
                        </p>

                        <template
                            v-if="
                                gamification.next_level
                            "
                        >
                            <p
                                class="mt-1 font-semibold"
                            >
                                {{
                                    gamification
                                        .next_level
                                        .name
                                }}
                            </p>

                            <p
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Faltan
                                {{
                                    gamification.points_to_next_level.toLocaleString(
                                        'es-MX',
                                    )
                                }}
                                puntos
                            </p>
                        </template>

                        <p
                            v-else
                            class="mt-1 font-semibold"
                        >
                            ¡Nivel máximo!
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Código QR personal -->

        <div
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between"
            >

                <!-- Información -->

                <div
                    class="flex-1"
                >
                    <h2
                        class="text-lg font-semibold"
                    >
                        Mi código QR
                    </h2>

                    <p
                        class="mt-1 max-w-xl text-sm text-muted-foreground"
                    >
                        Presenta este código en
                        taquilla para identificar tu
                        cuenta, acumular puntos y
                        asociar tus compras.
                    </p>

                    <div
                        class="mt-4 rounded-lg border border-sidebar-border/70 bg-muted/30 p-4 dark:border-sidebar-border"
                    >
                        <p
                            class="text-sm font-medium"
                        >
                            Código personal
                        </p>

                        <p
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Este código QR es único y
                            permanente para tu cuenta.
                        </p>
                    </div>
                </div>

                <!-- QR -->

                <div
                    class="flex justify-center md:justify-end"
                >
                    <div
                        v-if="userQrCode"
                        class="rounded-xl border border-sidebar-border bg-white p-4 shadow-sm"
                    >
                        <img
                            :src="userQrCode"
                            alt="Código QR personal"
                            class="h-56 w-56"
                        />
                    </div>

                    <div
                        v-else-if="user.qr_token"
                        class="flex h-56 w-56 items-center justify-center rounded-xl border border-dashed border-sidebar-border"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            Generando QR...
                        </span>
                    </div>

                    <div
                        v-else
                        class="flex h-56 w-56 items-center justify-center rounded-xl border border-dashed border-sidebar-border p-6 text-center"
                    >
                        <span
                            class="text-sm text-muted-foreground"
                        >
                            No hay un código QR
                            disponible para esta
                            cuenta.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario -->

        <Form
            v-bind="ProfileController.update.form()"
            :options="{
                forceFormData: true,
            }"
            enctype="multipart/form-data"
            class="space-y-6"
            v-slot="{
                errors,
                processing,
            }"
        >

            <!-- Avatar -->

            <div class="grid gap-4">
                <Label for="avatar">
                    Foto de perfil
                </Label>

                <div
                    class="flex items-center gap-5"
                >

                    <!-- Vista previa -->

                    <div
                        class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted"
                    >
                        <img
                            v-if="
                                avatarPreview
                            "
                            :src="
                                avatarPreview
                            "
                            alt="Vista previa del avatar"
                            class="h-full w-full object-cover"
                        />

                        <img
                            v-else-if="
                                profile?.avatar
                            "
                            :src="`/storage/${profile.avatar}`"
                            alt="Avatar actual"
                            class="h-full w-full object-cover"
                        />

                        <span
                            v-else
                            class="text-xs text-muted-foreground"
                        >
                            Sin foto
                        </span>
                    </div>

                    <!-- Selector -->

                    <div
                        class="flex-1"
                    >
                        <Input
                            id="avatar"
                            type="file"
                            name="avatar"
                            accept="image/jpeg,image/png,image/webp"
                            @change="
                                handleAvatarChange
                            "
                        />

                        <p
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            JPG, PNG o WebP. Máximo 2 MB.
                        </p>

                        <InputError
                            class="mt-2"
                            :message="
                                errors.avatar
                            "
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
                    :default-value="
                        user.name
                    "
                    required
                    autocomplete="name"
                    placeholder="Nombre completo"
                />

                <InputError
                    class="mt-2"
                    :message="
                        errors.name
                    "
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
                    :default-value="
                        user.email
                    "
                    required
                    autocomplete="username"
                    placeholder="Correo electrónico"
                />

                <InputError
                    class="mt-2"
                    :message="
                        errors.email
                    "
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
                    :default-value="
                        profile?.phone ??
                        ''
                    "
                    autocomplete="tel"
                    placeholder="Número de teléfono"
                />

                <InputError
                    class="mt-2"
                    :message="
                        errors.phone
                    "
                />
            </div>

            <!-- Fecha de nacimiento -->

            <div class="grid gap-2">
                <Label
                    for="birth_date"
                >
                    Fecha de nacimiento
                </Label>

                <Input
                    id="birth_date"
                    type="date"
                    class="mt-1 block w-full"
                    name="birth_date"
                    :default-value="
                        profile?.birth_date ??
                        ''
                    "
                    autocomplete="bday"
                />

                <InputError
                    class="mt-2"
                    :message="
                        errors.birth_date
                    "
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
                        :default-value="
                            profile?.city ??
                            ''
                        "
                        autocomplete="address-level2"
                        placeholder="Ciudad"
                    />

                    <InputError
                        class="mt-2"
                        :message="
                            errors.city
                        "
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
                        :default-value="
                            profile?.country ??
                            ''
                        "
                        autocomplete="country-name"
                        placeholder="País"
                    />

                    <InputError
                        class="mt-2"
                        :message="
                            errors.country
                        "
                    />
                </div>
            </div>

            <!-- Verificación de correo -->

            <!-- @chisel-email-verification -->

            <div
                v-if="
                    page.props
                        .mustVerifyEmail &&
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
                        page.props
                            .status ===
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

            <div
                class="flex items-center gap-4"
            >
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