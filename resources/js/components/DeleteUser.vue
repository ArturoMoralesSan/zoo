<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import {
    AlertTriangle,
    ShieldAlert,
    Trash2,
} from 'lucide-vue-next';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <AdminCard
        title="Eliminar cuenta"
        description="Elimina permanentemente tu cuenta y los recursos asociados."
        :icon="Trash2"
    >
        <div
            class="rounded-xl border border-[#F2D4D1] bg-[#FFF7F6] p-4 sm:p-5"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#FCE7E5] text-[#B9382F]"
                >
                    <AlertTriangle :size="21" />
                </div>

                <div class="min-w-0 flex-1 space-y-1">
                    <p class="text-sm font-semibold text-[#A52F28]">
                        Acción permanente
                    </p>

                    <p class="text-sm leading-6 text-[#81504B]">
                        Al eliminar tu cuenta, tus datos y recursos asociados
                        se eliminarán permanentemente. Esta acción no se puede
                        deshacer.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex justify-end border-t border-[#F2D4D1] pt-4">
                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                            class="rounded-lg"
                        >
                            <Trash2 class="mr-2 size-4" />
                            Eliminar cuenta
                        </Button>
                    </DialogTrigger>

                    <DialogContent class="rounded-2xl border-[#DCE7DF] sm:max-w-md">
                        <Form
                            v-bind="ProfileController.destroy.form()"
                            reset-on-success
                            @error="() => passwordInput?.focus()"
                            :options="{ preserveScroll: true }"
                            class="space-y-6"
                            v-slot="{
                                errors,
                                processing,
                                reset,
                                clearErrors,
                            }"
                        >
                            <DialogHeader class="space-y-3">
                                <div
                                    class="flex size-12 items-center justify-center rounded-xl bg-[#FCE7E5] text-[#B9382F]"
                                >
                                    <ShieldAlert :size="24" />
                                </div>

                                <DialogTitle class="text-lg font-semibold text-[#244334]">
                                    ¿Deseas eliminar tu cuenta?
                                </DialogTitle>

                                <DialogDescription class="text-sm leading-6 text-[#718477]">
                                    Esta acción eliminará permanentemente tu
                                    cuenta, tus recursos y tus datos. Para
                                    confirmar, ingresa tu contraseña actual.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="form-field">
                                <Label
                                    for="password"
                                    class="form-label"
                                >
                                    Contraseña actual
                                    <span class="form-required">*</span>
                                </Label>

                                <PasswordInput
                                    id="password"
                                    name="password"
                                    ref="passwordInput"
                                    class="form-input"
                                    autocomplete="current-password"
                                    placeholder="Ingresa tu contraseña"
                                />

                                <InputError
                                    :message="errors.password"
                                    class="form-error"
                                />
                            </div>

                            <DialogFooter class="flex-col-reverse gap-2 sm:flex-row">
                                <DialogClose as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="rounded-lg border-[#DCE7DF] text-[#435D4C]"
                                        @click="
                                            () => {
                                                clearErrors();
                                                reset();
                                            }
                                        "
                                    >
                                        Cancelar
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                    data-test="confirm-delete-user-button"
                                    class="rounded-lg"
                                >
                                    <span
                                        v-if="processing"
                                        class="flex items-center gap-2"
                                    >
                                        <span
                                            class="size-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                        />
                                        Eliminando...
                                    </span>

                                    <span
                                        v-else
                                        class="flex items-center gap-2"
                                    >
                                        <Trash2 class="size-4" />
                                        Confirmar eliminación
                                    </span>
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AdminCard>
</template>