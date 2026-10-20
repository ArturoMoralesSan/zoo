<script setup lang="ts">
import {
    Form,
    Head,
    Link,
    usePage,
} from '@inertiajs/vue3';
import QRCode from 'qrcode';
import {
    computed,
    onMounted,
    ref,
} from 'vue';
import {
    ArrowLeft,
    Award,
    CalendarDays,
    Check,
    CircleUserRound,
    Globe,
    Heart,
    Image,
    Mail,
    MapPin,
    QrCode,
    Save,
    ShieldCheck,
    Sparkles,
    Star,
    UserRound,
} from 'lucide-vue-next';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import PageHeader from '@/components/admin/PageHeader.vue';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

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
    () => page.props.profile as Profile | null,
);

const gamification = computed(
    () => page.props.gamification as Gamification | undefined,
);

/*
|--------------------------------------------------------------------------
| QR del usuario
|--------------------------------------------------------------------------
*/

const userQrCode = ref<string | null>(null);

const generateUserQrCode = async (): Promise<void> => {
    if (!user.value.qr_token) {
        console.warn('El usuario no tiene qr_token.');
        return;
    }

    try {
        userQrCode.value = await QRCode.toDataURL(
            user.value.qr_token,
            {
                width: 400,
                margin: 2,
                errorCorrectionLevel: 'H',
            },
        );
    } catch (error) {
        console.error('Error generando QR del usuario:', error);
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

const avatarPreview = ref<string | null>(null);

const handleAvatarChange = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    if (!target.files || !target.files[0]) {
        avatarPreview.value = null;
        return;
    }

    if (avatarPreview.value) {
        URL.revokeObjectURL(avatarPreview.value);
    }

    avatarPreview.value = URL.createObjectURL(target.files[0]);
};
</script>

<template>
    <Head title="Configuración del perfil" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Cabecera -->

        <PageHeader
            title="Mi perfil"
            description="Administra tu información personal, consulta tus puntos y accede a tu código QR."
            section="Configuración de cuenta"
            :icon="CircleUserRound"
        />

        <!-- Gamificación -->

        <AdminCard
            v-if="gamification"
            title="Mi progreso"
            description="Consulta tus puntos acumulados y tu avance en el sistema de recompensas."
            :icon="Award"
        >
            <div class="flex flex-col gap-6">
                <!-- Nivel y puntos -->

                <div
                    class="flex flex-col gap-4 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#E1EFE5] text-[#187744]"
                        >
                            <Award :size="28" />
                        </div>

                        <div>
                            <p class="text-sm text-[#718477]">
                                Nivel actual
                            </p>

                            <h2 class="mt-1 text-xl font-semibold text-[#244334]">
                                {{
                                    gamification.current_level?.name ??
                                    'Sin nivel'
                                }}
                            </h2>
                        </div>
                    </div>

                    <div class="sm:text-right">
                        <p class="text-3xl font-bold tracking-tight text-[#187744]">
                            {{
                                gamification.points.toLocaleString('es-MX')
                            }}
                        </p>

                        <p class="mt-1 text-sm text-[#718477]">
                            puntos acumulados
                        </p>
                    </div>
                </div>

                <!-- Barra de progreso -->

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-[#244334]">
                            Progreso al siguiente nivel
                        </span>

                        <span class="rounded-full bg-[#E1EFE5] px-3 py-1 text-sm font-semibold text-[#187744]">
                            {{ gamification.progress }}%
                        </span>
                    </div>

                    <div
                        class="h-3 w-full overflow-hidden rounded-full bg-[#E5ECE7]"
                    >
                        <div
                            class="h-full rounded-full bg-[#187744] transition-all duration-500"
                            :style="{
                                width: `${gamification.progress}%`,
                            }"
                        />
                    </div>
                </div>

                <!-- Niveles -->

                <div class="grid gap-4 md:grid-cols-2">
                    <div
                        class="rounded-xl border border-[#DCE7DF] bg-white p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E1EFE5] text-[#187744]"
                            >
                                <Star :size="20" />
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-[#718477]">
                                    Nivel actual
                                </p>

                                <p class="mt-1 font-semibold text-[#244334]">
                                    {{
                                        gamification.current_level?.name ??
                                        'Sin nivel'
                                    }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="gamification.current_level"
                            class="mt-4 text-sm text-[#718477]"
                        >
                            Desde
                            {{
                                gamification.current_level.min_points.toLocaleString(
                                    'es-MX',
                                )
                            }}
                            puntos
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-[#DCE7DF] bg-white p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E1EFE5] text-[#187744]"
                            >
                                <Sparkles :size="20" />
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-[#718477]">
                                    Siguiente nivel
                                </p>

                                <template v-if="gamification.next_level">
                                    <p class="mt-1 font-semibold text-[#244334]">
                                        {{ gamification.next_level.name }}
                                    </p>
                                </template>

                                <p
                                    v-else
                                    class="mt-1 font-semibold text-[#187744]"
                                >
                                    ¡Nivel máximo!
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="gamification.next_level"
                            class="mt-4 text-sm text-[#718477]"
                        >
                            Faltan
                            {{
                                gamification.points_to_next_level.toLocaleString(
                                    'es-MX',
                                )
                            }}
                            puntos
                        </p>
                    </div>
                </div>
            </div>
        </AdminCard>

        <!-- Código QR -->

        <AdminCard
            title="Mi código QR"
            description="Identifícate en taquilla para acumular puntos y asociar tus compras a tu cuenta."
            :icon="QrCode"
        >
            <div
                class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex-1">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E1EFE5] text-[#187744]"
                        >
                            <ShieldCheck :size="21" />
                        </div>

                        <div>
                            <h3 class="font-semibold text-[#244334]">
                                Código personal
                            </h3>

                            <p class="mt-1 max-w-xl text-sm leading-6 text-[#718477]">
                                Este código QR es único para tu cuenta. Preséntalo
                                al realizar tus compras para facilitar tu
                                identificación y el registro de tus puntos.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-5 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                    >
                        <div class="flex items-center gap-3">
                            <Check
                                class="h-5 w-5 shrink-0 text-[#187744]"
                            />

                            <p class="text-sm text-[#435D4C]">
                                Conserva tu cuenta actualizada para disfrutar de
                                las ventajas del programa de recompensas.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center lg:justify-end">
                    <div
                        v-if="userQrCode"
                        class="rounded-2xl border border-[#DCE7DF] bg-white p-4"
                    >
                        <img
                            :src="userQrCode"
                            alt="Código QR personal"
                            class="h-52 w-52 sm:h-56 sm:w-56"
                        />
                    </div>

                    <div
                        v-else-if="user.qr_token"
                        class="flex h-56 w-56 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#BFCFC6] bg-white"
                    >
                        <QrCode class="h-8 w-8 text-[#718477]" />

                        <span class="text-sm text-[#718477]">
                            Generando QR...
                        </span>
                    </div>

                    <div
                        v-else
                        class="flex h-56 w-56 flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-[#BFCFC6] bg-white p-6 text-center"
                    >
                        <QrCode class="h-8 w-8 text-[#84958A]" />

                        <span class="text-sm text-[#718477]">
                            No hay un código QR disponible para esta cuenta.
                        </span>
                    </div>
                </div>
            </div>
        </AdminCard>

        <!-- Formulario del perfil -->

        <Form
            v-bind="ProfileController.update.form()"
            :options="{
                forceFormData: true,
            }"
            enctype="multipart/form-data"
            class="flex flex-col gap-5"
            v-slot="{ errors, processing }"
        >
            <!-- Foto de perfil -->

            <AdminCard
                title="Foto de perfil"
                description="Personaliza tu cuenta con una imagen de perfil."
                :icon="Image"
            >
                <div
                    class="flex flex-col gap-5 sm:flex-row sm:items-center"
                >
                    <div
                        class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[#DCE7DF] bg-[#F7FAF8]"
                    >
                        <img
                            v-if="avatarPreview"
                            :src="avatarPreview"
                            alt="Vista previa del avatar"
                            class="h-full w-full object-cover"
                        />

                        <img
                            v-else-if="profile?.avatar"
                            :src="`/storage/${profile.avatar}`"
                            alt="Avatar actual"
                            class="h-full w-full object-cover"
                        />

                        <div
                            v-else
                            class="flex flex-col items-center gap-2 text-[#84958A]"
                        >
                            <CircleUserRound :size="32" />

                            <span class="text-xs">
                                Sin foto
                            </span>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <Label
                            for="avatar"
                            class="mb-2 block text-sm font-medium text-[#244334]"
                        >
                            Seleccionar imagen
                        </Label>

                        <Input
                            id="avatar"
                            type="file"
                            name="avatar"
                            accept="image/jpeg,image/png,image/webp"
                            @change="handleAvatarChange"
                        />

                        <p class="mt-2 text-xs leading-5 text-[#718477]">
                            Formatos permitidos: JPG, PNG o WebP. Tamaño máximo: 2 MB.
                        </p>

                        <InputError
                            class="mt-2"
                            :message="errors.avatar"
                        />
                    </div>
                </div>
            </AdminCard>

            <!-- Información personal -->

            <AdminCard
                title="Información personal"
                description="Mantén actualizados tus datos de contacto y tu información básica."
                :icon="UserRound"
            >
                <div class="form-grid">
                    <!-- Nombre -->

                    <div class="form-field form-field-full">
                        <Label for="name" class="form-label">
                            Nombre completo
                            <span class="form-required">*</span>
                        </Label>

                        <Input
                            id="name"
                            class="form-input"
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

                    <div class="form-field form-field-full">
                        <Label for="email" class="form-label">
                            Correo electrónico
                            <span class="form-required">*</span>
                        </Label>

                        <Input
                            id="email"
                            type="email"
                            class="form-input"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="correo@ejemplo.com"
                        />

                        <InputError
                            class="mt-2"
                            :message="errors.email"
                        />

                        <!-- Verificación del correo -->

                        <div
                            v-if="
                                page.props.mustVerifyEmail &&
                                !user.email_verified_at
                            "
                            class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <Mail
                                    class="mt-0.5 h-5 w-5 shrink-0 text-amber-700"
                                />

                                <div>
                                    <p class="text-sm font-medium text-amber-900">
                                        Correo electrónico sin verificar
                                    </p>

                                    <p class="mt-1 text-sm leading-5 text-amber-800">
                                        Tu dirección de correo electrónico aún no está verificada.
                                    </p>

                                    <Link
                                        :href="send()"
                                        as="button"
                                        class="mt-2 text-sm font-semibold text-amber-900 underline underline-offset-4 hover:text-amber-700"
                                    >
                                        Volver a enviar el correo de verificación
                                    </Link>

                                    <p
                                        v-if="
                                            page.props.status ===
                                            'verification-link-sent'
                                        "
                                        class="mt-3 text-sm font-medium text-[#187744]"
                                    >
                                        Se ha enviado un nuevo enlace de verificación a tu correo electrónico.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="user.email_verified_at"
                            class="mt-3 flex items-center gap-2 text-sm text-[#187744]"
                        >
                            <Check class="h-4 w-4" />

                            <span>
                                Correo electrónico verificado
                            </span>
                        </div>
                    </div>

                    <!-- Teléfono -->

                    <div class="form-field">
                        <Label for="phone" class="form-label">
                            Teléfono
                        </Label>

                        <Input
                            id="phone"
                            type="tel"
                            class="form-input"
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

                    <div class="form-field">
                        <Label for="birth_date" class="form-label">
                            Fecha de nacimiento
                        </Label>

                        <Input
                            id="birth_date"
                            type="date"
                            class="form-input"
                            name="birth_date"
                            :default-value="profile?.birth_date ?? ''"
                            autocomplete="bday"
                        />

                        <InputError
                            class="mt-2"
                            :message="errors.birth_date"
                        />
                    </div>
                </div>
            </AdminCard>

            <!-- Ubicación -->

            <AdminCard
                title="Ubicación"
                description="Agrega tu ciudad y país para completar tu información personal."
                :icon="MapPin"
            >
                <div class="form-grid">
                    <!-- Ciudad -->

                    <div class="form-field">
                        <Label for="city" class="form-label">
                            Ciudad
                        </Label>

                        <Input
                            id="city"
                            type="text"
                            class="form-input"
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

                    <div class="form-field">
                        <Label for="country" class="form-label">
                            País
                        </Label>

                        <Input
                            id="country"
                            type="text"
                            class="form-input"
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
            </AdminCard>

            <!-- Confirmación y acciones -->

            <div class="form-info">
                <div class="form-info-icon">
                    <ShieldCheck class="h-5 w-5" />
                </div>

                <p class="form-info-text">
                    Revisa tus datos antes de guardar. Tu información actualizada
                    ayudará a identificar tu cuenta y mantener tus datos de contacto al día.
                </p>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="processing"
                    loading-text="Guardando..."
                >
                    Guardar cambios
                </AdminButton>
            </div>
        </Form>

        <!-- Eliminación de cuenta -->

        <div class="border-t border-[#DCE7DF] pt-5">
            <DeleteUser />
        </div>
    </div>
</template>