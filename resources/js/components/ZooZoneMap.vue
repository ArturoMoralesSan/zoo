<script setup lang="ts">
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';

interface PolygonGeometry {
    type: 'Polygon';
    coordinates: number[][][];
}

const props = withDefaults(
    defineProps<{
        modelValue?: PolygonGeometry | null;
        center?: [number, number];
        zoom?: number;
        readonly?: boolean;
        height?: string;
    }>(),
    {
        modelValue: null,
        center: () => [24.03, -104.67],
        zoom: 16,
        readonly: false,
        height: '500px',
    },
);

const emit = defineEmits<{
    (
        event: 'update:modelValue',
        value: PolygonGeometry | null,
    ): void;
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let drawnItems: L.FeatureGroup | null = null;
let drawControl: L.Control.Draw | null = null;

let syncingFromParent = false;

const geometryToLayer = (
    geometry: PolygonGeometry,
): L.Polygon | null => {
    if (
        !geometry ||
        geometry.type !== 'Polygon' ||
        !geometry.coordinates?.length
    ) {
        return null;
    }

    const ring = geometry.coordinates[0];

    if (!ring?.length) {
        return null;
    }

    const latLngs = ring.map(
        ([longitude, latitude]) =>
            [
                latitude,
                longitude,
            ] as L.LatLngExpression,
    );

    return L.polygon(latLngs);
};

const layerToGeometry = (
    layer: L.Layer,
): PolygonGeometry | null => {
    if (!(layer instanceof L.Polygon)) {
        return null;
    }

    const geoJson = layer.toGeoJSON();

    if (geoJson.geometry.type !== 'Polygon') {
        return null;
    }

    return {
        type: 'Polygon',
        coordinates: geoJson.geometry.coordinates,
    };
};

const getCurrentLayer = (): L.Layer | null => {
    if (!drawnItems) {
        return null;
    }

    let currentLayer: L.Layer | null = null;

    drawnItems.eachLayer((layer) => {
        if (!currentLayer) {
            currentLayer = layer;
        }
    });

    return currentLayer;
};

const emitCurrentGeometry = () => {
    if (syncingFromParent) {
        return;
    }

    const layer = getCurrentLayer();

    if (!layer) {
        emit('update:modelValue', null);
        return;
    }

    emit(
        'update:modelValue',
        layerToGeometry(layer),
    );
};

const clearLayers = () => {
    if (!drawnItems) {
        return;
    }

    drawnItems.clearLayers();
};

const loadGeometry = () => {
    if (!map || !drawnItems) {
        return;
    }

    syncingFromParent = true;

    clearLayers();

    if (props.modelValue) {
        const layer = geometryToLayer(
            props.modelValue,
        );

        if (layer) {
            drawnItems.addLayer(layer);

            map.fitBounds(
                layer.getBounds(),
                {
                    padding: [30, 30],
                },
            );
        }
    }

    syncingFromParent = false;
};

const initializeMap = async () => {
    await nextTick();

    if (!mapElement.value || map) {
        return;
    }

    map = L.map(
        mapElement.value,
    ).setView(
        props.center,
        props.zoom,
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors',
        },
    ).addTo(map);

    drawnItems = new L.FeatureGroup();

    map.addLayer(drawnItems);

    if (!props.readonly) {
        drawControl = new L.Control.Draw({
            position: 'topleft',

            edit: {
                featureGroup: drawnItems,
                remove: true,
            },

            draw: {
                polygon: {
                    allowIntersection: false,
                    showArea: true,
                    showLength: true,

                    shapeOptions: {
                        weight: 3,
                    },
                },

                polyline: false,
                rectangle: false,
                circle: false,
                circlemarker: false,
                marker: false,
            },
        });

        map.addControl(drawControl);

        map.on(
            L.Draw.Event.CREATED,
            (event: L.DrawEvents.Created) => {
                if (!drawnItems) {
                    return;
                }

                clearLayers();

                const layer =
                    event.layer;

                drawnItems.addLayer(
                    layer,
                );

                emitCurrentGeometry();
            },
        );

        map.on(
            L.Draw.Event.EDITED,
            () => {
                emitCurrentGeometry();
            },
        );

        map.on(
            L.Draw.Event.DELETED,
            () => {
                emitCurrentGeometry();
            },
        );
    }

    loadGeometry();

    setTimeout(() => {
        map?.invalidateSize();
    }, 100);
};

watch(
    () => props.modelValue,
    (
        newGeometry,
        oldGeometry,
    ) => {
        if (
            JSON.stringify(newGeometry) ===
            JSON.stringify(oldGeometry)
        ) {
            return;
        }

        if (!map || !drawnItems) {
            return;
        }

        loadGeometry();
    },
    {
        deep: true,
    },
);

onMounted(() => {
    initializeMap();
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }

    drawnItems = null;
    drawControl = null;
});
</script>

<template>
    <div class="space-y-3">
        <div
            ref="mapElement"
            class="w-full overflow-hidden rounded-lg border border-sidebar-border"
            :style="{ height }"
        />

        <div
            v-if="!readonly"
            class="rounded-lg border border-sidebar-border bg-background p-3"
        >
            <p class="text-sm font-medium">
                Dibujar zona
            </p>

            <p
                class="mt-1 text-xs text-muted-foreground"
            >
                Selecciona el icono de polígono en la
                barra de herramientas del mapa y haz
                clic en cada punto para delimitar la
                zona. Para terminar, haz clic en el
                primer punto.
            </p>
        </div>

        <div
            v-else
            class="rounded-lg border border-sidebar-border bg-background p-3"
        >
            <p class="text-sm text-muted-foreground">
                Vista de la zona en el mapa.
            </p>
        </div>
    </div>
</template>