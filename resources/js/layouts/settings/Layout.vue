<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    UserRound,
    ShieldCheck,
    Palette,
    ChevronRight,
    Settings2,
} from 'lucide-vue-next';

import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';

import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Perfil',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: 'Seguridad',
        href: editSecurity(),
        icon: ShieldCheck,
    }
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <div
            class="rounded-2xl border border-[#DCE7DF] bg-white p-5 sm:p-6"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#EAF3EC] text-[#187744]"
                >
                    <Settings2 :size="22" />
                </div>

                <div class="min-w-0 flex-1">
                    <Heading
                        title="Configuración"
                        description="Administra tu perfil y personaliza la configuración de tu cuenta."
                    />
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-5 lg:flex-row lg:items-start">
            <aside class="w-full shrink-0 lg:sticky lg:top-5 lg:w-60">
                <div
                    class="rounded-2xl border border-[#DCE7DF] bg-white p-3"
                >
                    <p
                        class="px-3 pb-3 pt-2 text-xs font-semibold uppercase tracking-wider text-[#84958A]"
                    >
                        Cuenta
                    </p>

                    <nav
                        class="flex flex-col gap-1"
                        aria-label="Configuración"
                    >
                        <Link
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            :href="item.href"
                            :class="[
                                'group flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition-colors duration-200',
                                isCurrentOrParentUrl(item.href)
                                    ? 'bg-[#EAF3EC] text-[#187744]'
                                    : 'text-[#435D4C] hover:bg-[#F2F7F4] hover:text-[#187744]',
                            ]"
                            :aria-current="
                                isCurrentOrParentUrl(item.href)
                                    ? 'page'
                                    : undefined
                            "
                        >
                            <component
                                :is="item.icon"
                                :size="19"
                                :class="[
                                    'shrink-0 transition-colors',
                                    isCurrentOrParentUrl(item.href)
                                        ? 'text-[#187744]'
                                        : 'text-[#84958A] group-hover:text-[#187744]',
                                ]"
                            />

                            <span class="flex-1">
                                {{ item.title }}
                            </span>

                            <ChevronRight
                                v-if="isCurrentOrParentUrl(item.href)"
                                :size="16"
                                class="shrink-0 text-[#187744]"
                            />
                        </Link>
                    </nav>
                </div>
            </aside>

            <Separator class="my-1 lg:hidden" />

            <main class="min-w-0 flex-1">
                <section
                    class="w-full space-y-5"
                >
                    <slot />
                </section>
            </main>
        </div>
    </div>
</template>