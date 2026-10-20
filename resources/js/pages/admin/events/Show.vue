<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    Edit,
    Image as ImageIcon,
    Info,
    MapPin,
    Star,
    Users,
    X,
} from 'lucide-vue-next';
import PageHeader from '@/components/admin/PageHeader.vue';
import admin from '@/routes/admin';

interface Zone {
    id: number;
    name: string;
    type: string | null;
}

interface EventItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    type: string | null;
    start_at: string | null;
    end_at: string | null;
    zoo_zone_id: number | null;
    image: string | null;
    capacity: number | null;
    is_featured: boolean;
    is_active: boolean;
    zone: Zone | null;
    created_at: string | null;
    updated_at: string | null;
}

const props = defineProps<{
    event: EventItem;
}>();

const formatDate = (value: string | null): string => {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const getStatusLabel = (): string => {
    if (!props.event.is_active) return 'Inactivo';
    if (!props.event.start_at) return 'Sin fecha';

    const now = new Date();
    const start = new Date(props.event.start_at);
    const end = props.event.end_at
        ? new Date(props.event.end_at)
        : null;

    if (start > now) return 'Próximo';
    if (end && end < now) return 'Finalizado';

    return 'En curso';
};

const getStatusClasses = (): string => {
    if (!props.event.is_active || !props.event.start_at) {
        return 'status-neutral';
    }

    const now = new Date();
    const start = new Date(props.event.start_at);
    const end = props.event.end_at
        ? new Date(props.event.end_at)
        : null;

    if (start > now) return 'status-upcoming';
    if (end && end < now) return 'status-finished';

    return 'status-active';
};

const getImageUrl = (image: string | null): string | null => {
    if (!image) return null;

    if (
        image.startsWith('http://') ||
        image.startsWith('https://') ||
        image.startsWith('/')
    ) {
        return image;
    }

    return `/storage/${image}`;
};
</script>

<template>
    <Head :title="event.name" />

    <div class="admin-page">
        <PageHeader
            :title="event.name"
            description="Información y detalles del evento."
            section="Eventos"
            :icon="CalendarDays"
            action-label="Regresar"
            :action-icon="ArrowLeft"
            :action-href="admin.events.index().url"
        />

        <!-- Estado del evento -->
        <div class="event-badges">
            <span class="event-status" :class="getStatusClasses()">
                <span class="event-status-dot" />
                {{ getStatusLabel() }}
            </span>

            <span v-if="event.is_featured" class="event-featured">
                <Star class="h-3.5 w-3.5" />
                Destacado
            </span>
        </div>

        <!-- Información principal -->
        <div class="event-layout">
            <!-- Imagen -->
            <section class="detail-card event-image-card">
                <div class="detail-card-header">
                    <div class="detail-icon">
                        <ImageIcon class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="detail-title">Imagen del evento</h2>
                        <p class="detail-description">
                            Imagen principal utilizada para identificar el evento.
                        </p>
                    </div>
                </div>

                <div v-if="event.image" class="event-image-wrapper">
                    <img
                        :src="getImageUrl(event.image) ?? ''"
                        :alt="event.name"
                        class="event-image"
                    />
                </div>

                <div v-else class="event-image-empty">
                    <ImageIcon class="h-10 w-10" />
                    <p>Sin imagen disponible</p>
                </div>
            </section>

            <!-- Datos generales -->
            <section class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-icon">
                        <Info class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="detail-title">Información del evento</h2>
                        <p class="detail-description">
                            Datos principales y configuración del evento.
                        </p>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Tipo</span>
                        <p class="detail-value">{{ event.type || '—' }}</p>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">
                            <MapPin class="h-3.5 w-3.5" />
                            Zona
                        </span>
                        <p class="detail-value">
                            {{ event.zone?.name ?? 'Sin zona asignada' }}
                        </p>
                        <p v-if="event.zone?.type" class="detail-muted">
                            {{ event.zone.type }}
                        </p>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">
                            <CalendarDays class="h-3.5 w-3.5" />
                            Fecha de inicio
                        </span>
                        <p class="detail-value">
                            {{ formatDate(event.start_at) }}
                        </p>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">
                            <Clock class="h-3.5 w-3.5" />
                            Fecha de finalización
                        </span>
                        <p class="detail-value">
                            {{ formatDate(event.end_at) }}
                        </p>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">
                            <Users class="h-3.5 w-3.5" />
                            Capacidad
                        </span>
                        <p class="detail-value">
                            {{
                                event.capacity !== null
                                    ? event.capacity.toLocaleString('es-MX')
                                    : 'Ilimitada'
                            }}
                        </p>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Estado</span>
                        <span class="event-status" :class="getStatusClasses()">
                            <span class="event-status-dot" />
                            {{ getStatusLabel() }}
                        </span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Visibilidad</span>
                        <p class="detail-value">
                            {{ event.is_active ? 'Activo' : 'Inactivo' }}
                        </p>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label">Evento destacado</span>
                        <p class="detail-value">
                            {{ event.is_featured ? 'Sí' : 'No' }}
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Descripción -->
        <section class="detail-card">
            <div class="detail-card-header">
                <div class="detail-icon">
                    <Info class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="detail-title">Descripción</h2>
                    <p class="detail-description">
                        Información descriptiva del evento.
                    </p>
                </div>
            </div>

            <p v-if="event.description" class="event-description">
                {{ event.description }}
            </p>

            <p v-else class="detail-empty-text">
                No se ha agregado una descripción para este evento.
            </p>
        </section>

        <!-- Información del registro -->
        <section class="detail-card">
            <div class="detail-card-header">
                <div class="detail-icon">
                    <CalendarDays class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="detail-title">Información del registro</h2>
                    <p class="detail-description">
                        Identificación y fechas de registro del evento.
                    </p>
                </div>
            </div>

            <div class="detail-grid detail-grid-four">
                <div class="detail-item">
                    <span class="detail-label">ID</span>
                    <p class="detail-value">#{{ event.id }}</p>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Slug</span>
                    <p class="detail-value break-all">{{ event.slug }}</p>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Creado</span>
                    <p class="detail-value">
                        {{ formatDate(event.created_at) }}
                    </p>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Última actualización</span>
                    <p class="detail-value">
                        {{ formatDate(event.updated_at) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Acciones -->
        <div class="detail-actions">
            <Link
                :href="admin.events.index().url"
                class="form-button form-button-secondary"
            >
                <X class="h-4 w-4" />
                Cancelar
            </Link>

            <Link
                :href="admin.events.edit(event.id).url"
                class="form-button form-button-primary"
            >
                <Edit class="h-4 w-4" />
                Editar evento
            </Link>
        </div>
    </div>
</template>