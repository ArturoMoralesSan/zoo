<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

interface GeoJsonGeometry {
    type: 'Polygon';
    coordinates: number[][][];
}

const props = withDefaults(
    defineProps<{
        geometry: GeoJsonGeometry | null;
        latitude?: number | null;
        longitude?: number | null;
        readonly?: boolean;
    }>(),
    {
        latitude: null,
        longitude: null,
        readonly: false,
    },
);

const emit = defineEmits<{
    'update:latitude': [value: number | null];
    'update:longitude': [value: number | null];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let zoneLayer: L.GeoJSON | null = null;
let marker: L.Marker | null = null;

const defaultCenter: L.LatLngExpression = [24.0277, -104.6532];

const markerIcon = L.divIcon({
    className: 'custom-map-marker',
    html: `
        <div style="
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            line-height: 1;
        ">
            📍
        </div>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 32],
});

const pointInPolygon = (
    latitude: number,
    longitude: number,
    coordinates: number[][],
): boolean => {
    let inside = false;

    for (
        let i = 0, j = coordinates.length - 1;
        i < coordinates.length;
        j = i++
    ) {
        const longitudeI = coordinates[i][0];
        const latitudeI = coordinates[i][1];

        const longitudeJ = coordinates[j][0];
        const latitudeJ = coordinates[j][1];

        const intersects =
            latitudeI > latitude !== latitudeJ > latitude &&
            longitude <
                ((longitudeJ - longitudeI) *
                    (latitude - latitudeI)) /
                    (latitudeJ - latitudeI) +
                    longitudeI;

        if (intersects) {
            inside = !inside;
        }
    }

    return inside;
};

const isInsideZone = (
    latitude: number,
    longitude: number,
): boolean => {
    if (!props.geometry?.coordinates?.[0]) {
        return true;
    }

    return pointInPolygon(
        latitude,
        longitude,
        props.geometry.coordinates[0],
    );
};

const updateCoordinates = (latLng: L.LatLng) => {
    emit('update:latitude', Number(latLng.lat.toFixed(7)));
    emit('update:longitude', Number(latLng.lng.toFixed(7)));
};

const createMarker = (
    latitude: number,
    longitude: number,
    draggable = true,
) => {
    if (!map) {
        return;
    }

    if (marker) {
        marker.remove();
        marker = null;
    }

    marker = L.marker([latitude, longitude], {
        icon: markerIcon,
        draggable: draggable && !props.readonly,
    }).addTo(map);

    marker.on('dragend', () => {
        if (!marker) {
            return;
        }

        const position = marker.getLatLng();

        if (!isInsideZone(position.lat, position.lng)) {
            if (
                props.latitude !== null &&
                props.longitude !== null
            ) {
                marker.setLatLng([
                    props.latitude,
                    props.longitude,
                ]);
            }

            return;
        }

        updateCoordinates(position);
    });
};

const clearMarker = () => {
    if (marker) {
        marker.remove();
        marker = null;
    }
};

const renderZone = () => {
    if (!map) {
        return;
    }

    if (zoneLayer) {
        zoneLayer.remove();
        zoneLayer = null;
    }

    clearMarker();

    if (!props.geometry) {
        map.setView(defaultCenter, 15);
        return;
    }

    zoneLayer = L.geoJSON(props.geometry as GeoJSON.GeoJsonObject, {
        style: {
            weight: 2,
            fillOpacity: 0.2,
        },
    }).addTo(map);

    const bounds = zoneLayer.getBounds();

    if (bounds.isValid()) {
        map.fitBounds(bounds, {
            padding: [30, 30],
        });
    }

    if (
        props.latitude !== null &&
        props.longitude !== null &&
        isInsideZone(props.latitude, props.longitude)
    ) {
        createMarker(
            props.latitude,
            props.longitude,
        );
    }
};

const handleMapClick = (event: L.LeafletMouseEvent) => {
    if (props.readonly) {
        return;
    }

    const latitude = event.latlng.lat;
    const longitude = event.latlng.lng;

    if (!isInsideZone(latitude, longitude)) {
        return;
    }

    createMarker(latitude, longitude);
    updateCoordinates(event.latlng);
};

onMounted(() => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value).setView(
        defaultCenter,
        15,
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors',
        },
    ).addTo(map);

    map.on('click', handleMapClick);

    renderZone();
});

watch(
    () => props.geometry,
    () => {
        renderZone();
    },
    {
        deep: true,
    },
);

watch(
    () => [props.latitude, props.longitude],
    ([latitude, longitude]) => {
        if (!map) {
            return;
        }

        if (
            latitude === null ||
            latitude === undefined ||
            longitude === null ||
            longitude === undefined
        ) {
            clearMarker();
            return;
        }

        if (!isInsideZone(latitude, longitude)) {
            return;
        }

        if (marker) {
            marker.setLatLng([
                latitude,
                longitude,
            ]);
        } else {
            createMarker(latitude, longitude);
        }
    },
);

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <div
        ref="mapElement"
        class="h-[400px] w-full rounded-lg overflow-hidden border border-sidebar-border"
    />
</template>

<style>
.custom-map-marker {
    background: transparent;
    border: none;
}
</style>