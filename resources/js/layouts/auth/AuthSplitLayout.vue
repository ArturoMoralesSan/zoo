
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const name = page.props.name;
</script>

<template>
    <div class="grid min-h-dvh bg-[#f3f8f4] lg:grid-cols-2">

        <!-- Panel izquierdo: fotografía -->
        <section
            class="relative hidden min-h-dvh overflow-hidden bg-[#123022] lg:block"
        >
            <!-- Imagen de fondo -->
            <div
                class="absolute inset-0 bg-cover bg-top"
                style="background-image: url('/images/login-background.png')"
            ></div>

            <!-- Degradado inferior -->
            <div
                class="absolute inset-0"
                style="
                    background: linear-gradient(
                        to top,
                        rgba(18, 48, 34, 0.90) 0%,
                        rgba(18, 48, 34, 0.45) 35%,
                        transparent 75%
                    );
                "
            ></div>

            <!-- Contenido inferior -->
            <div
                class="absolute inset-x-0 bottom-0 z-10 flex flex-col items-start p-12 xl:p-16"
            >
                <!-- Logotipo blanco para escritorio -->
                <img
                    src="/images/zooapp-logo.png"
                    alt="ZooApp"
                    class="mb-7 h-auto w-[260px] max-w-full object-contain object-left xl:w-[320px]"
                />

                <h1 class="text-3xl font-bold tracking-tight text-white xl:text-4xl">
                    Panel de administración
                </h1>

                <p class="mt-4 max-w-lg text-base leading-relaxed text-white/80">
                    Gestiona especies, taquilla, gamificación y eventos del
                    zoológico Sahuatoba.
                </p>

                <div class="mt-10 text-xs text-white/50">
                    {{ name }}
                </div>
            </div>
        </section>

        <!-- Panel derecho -->
        <section
            class="flex min-h-dvh flex-col items-center justify-center bg-[#f3f8f4] px-6 py-10 sm:px-10 lg:px-14 xl:px-20"
        >
            <!-- Logo negro para dispositivos móviles -->
            <div class="mb-8 flex justify-center lg:hidden">
                <Link :href="home()">
                    <img
                        src="/images/zooapp-logo.png"
                        alt="ZooApp"
                        class="h-auto w-[190px] object-contain brightness-0"
                    />
                </Link>
            </div>

            <!-- Card del formulario -->
            <div
                class="mx-auto w-full max-w-md rounded-2xl border border-[#d2ddd7] bg-white p-8 shadow-[0_8px_30px_rgba(2,63,29,0.05)] sm:p-10"
            >
                <!-- Encabezado -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold tracking-tight text-[#023f1d]">
                        {{ title }}
                    </h2>

                    <p
                        v-if="description"
                        class="mt-2 text-sm leading-relaxed text-[#023f1d]/65"
                    >
                        {{ description }}
                    </p>
                </div>

                <!-- Contenido del formulario -->
                <slot />
            </div>
        </section>
    </div>
</template>
