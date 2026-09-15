<script setup lang="ts">
import L from 'leaflet';
import {
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import 'leaflet/dist/leaflet.css';

interface GeoJsonGeometry {
    type: 'Polygon';
    coordinates: number[][][];
}

interface MapImageBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

const props = withDefaults(
    defineProps<{
        geometry: GeoJsonGeometry | null;
        latitude?: number | null;
        longitude?: number | null;
        readonly?: boolean;
        mapImage?: File | string | null;
        mapImageBounds?: MapImageBounds | null;
    }>(),
    {
        latitude: null,
        longitude: null,
        readonly: false,
        mapImage: null,
        mapImageBounds: null,
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
let imageOverlay: L.ImageOverlay | null = null;

let currentImageUrl: string | null = null;
let imageUrlIsObjectUrl = false;

const defaultCenter: L.LatLngExpression = [
    24.0277,
    -104.6532,
];

/*
|--------------------------------------------------------------------------
| PANES
|--------------------------------------------------------------------------
|
| Zona   = 400
| Imagen = 450
| Marker = 700
|
| El marker siempre queda encima de la imagen.
|
*/

const createMapPanes = (): void => {
    if (!map) {
        return;
    }

    /*
     * Zona
     */
    map.createPane('zooZonePane');

    const zonePane =
        map.getPane('zooZonePane');

    if (zonePane) {
        zonePane.style.zIndex = '400';

        /*
         * La zona no debe bloquear
         * los clicks del mapa.
         */
        zonePane.style.pointerEvents = 'none';
    }

    /*
     * Imagen del zoológico
     */
    map.createPane('zooImagePane');

    const imagePane =
        map.getPane('zooImagePane');

    if (imagePane) {
        imagePane.style.zIndex = '450';

        /*
         * La imagen solamente es visual.
         * Nunca debe bloquear los clicks.
         */
        imagePane.style.pointerEvents = 'none';
    }

    /*
     * Marker
     */
    map.createPane('zooMarkerPane');

    const markerPane =
        map.getPane('zooMarkerPane');

    if (markerPane) {
        markerPane.style.zIndex = '700';
        markerPane.style.pointerEvents = 'auto';
    }
};

/*
|--------------------------------------------------------------------------
| ICONO DEL MARKER
|--------------------------------------------------------------------------
*/

const markerIcon = L.divIcon({
    className: 'custom-map-marker',

    html: `
        <div class="map-marker-pin">
            📍
        </div>
    `,

    iconSize: [40, 40],

    iconAnchor: [20, 40],
});

/*
|--------------------------------------------------------------------------
| POLÍGONO
|--------------------------------------------------------------------------
*/

/**
 * Determina si un punto está dentro
 * del polígono de la zona.
 *
 * coordinates:
 *
 * [
 *     [longitude, latitude],
 *     [longitude, latitude],
 *     ...
 * ]
 */
const pointInPolygon = (
    latitude: number,
    longitude: number,
    coordinates: number[][],
): boolean => {
    let inside = false;

    for (
        let i = 0,
            j = coordinates.length - 1;
        i < coordinates.length;
        j = i++
    ) {
        const longitudeI =
            Number(coordinates[i][0]);

        const latitudeI =
            Number(coordinates[i][1]);

        const longitudeJ =
            Number(coordinates[j][0]);

        const latitudeJ =
            Number(coordinates[j][1]);

        const denominator =
            latitudeJ - latitudeI;

        /*
         * Segmento horizontal.
         */
        if (denominator === 0) {
            continue;
        }

        const intersects =
            latitudeI > latitude !==
                latitudeJ > latitude &&
            longitude <
                ((longitudeJ -
                    longitudeI) *
                    (latitude -
                        latitudeI)) /
                    denominator +
                    longitudeI;

        if (intersects) {
            inside = !inside;
        }
    }

    return inside;
};

/**
 * La ZONA es la autoridad para
 * determinar si un marker es válido.
 *
 * IMPORTANTE:
 *
 * mapImageBounds NO se utiliza aquí.
 *
 * Esto permite colocar un marker:
 *
 *   ✓ encima de la imagen
 *   ✓ fuera de la imagen
 *   ✓ siempre que esté dentro de la zona
 */
const isInsideZone = (
    latitude: number,
    longitude: number,
): boolean => {
    const ring =
        props.geometry?.coordinates?.[0];

    /*
     * Si todavía no existe geometría,
     * no podemos restringir el punto.
     */
    if (
        !ring ||
        ring.length < 3
    ) {
        return true;
    }

    return pointInPolygon(
        latitude,
        longitude,
        ring,
    );
};

/*
|--------------------------------------------------------------------------
| BOUNDS DEL PLANO
|--------------------------------------------------------------------------
|
| Estos bounds SOLO sirven para dibujar
| la imagen sobre el mapa.
|
| NO sirven para decidir dónde puede
| colocarse el marker.
|
*/

const getImageBounds = (): L.LatLngBounds | null => {
    const bounds =
        props.mapImageBounds;

    if (!bounds) {
        return null;
    }

    const north =
        Number(bounds.north);

    const south =
        Number(bounds.south);

    const east =
        Number(bounds.east);

    const west =
        Number(bounds.west);

    if (
        !Number.isFinite(north) ||
        !Number.isFinite(south) ||
        !Number.isFinite(east) ||
        !Number.isFinite(west)
    ) {
        return null;
    }

    if (
        north <= south ||
        east <= west
    ) {
        return null;
    }

    return L.latLngBounds(
        [
            south,
            west,
        ],
        [
            north,
            east,
        ],
    );
};

/*
|--------------------------------------------------------------------------
| COORDENADAS
|--------------------------------------------------------------------------
*/

const updateCoordinates = (
    latLng: L.LatLng,
): void => {
    emit(
        'update:latitude',
        Number(
            latLng.lat.toFixed(7),
        ),
    );

    emit(
        'update:longitude',
        Number(
            latLng.lng.toFixed(7),
        ),
    );
};

/*
|--------------------------------------------------------------------------
| IMAGEN
|--------------------------------------------------------------------------
*/

const resolveImageUrl = (
    image: File | string,
): string => {
    /*
     * Imagen existente almacenada
     * en la base de datos.
     */
    if (
        typeof image === 'string'
    ) {
        if (
            image.startsWith(
                'http://',
            ) ||
            image.startsWith(
                'https://',
            ) ||
            image.startsWith(
                'blob:',
            ) ||
            image.startsWith(
                'data:',
            ) ||
            image.startsWith('/')
        ) {
            return image;
        }

        return `/storage/${image}`;
    }

    /*
     * Imagen nueva seleccionada
     * desde el formulario.
     */
    return URL.createObjectURL(
        image,
    );
};

const clearImage = (): void => {
    if (
        imageOverlay &&
        map
    ) {
        imageOverlay.removeFrom(map);
    }

    imageOverlay = null;

    if (
        currentImageUrl &&
        imageUrlIsObjectUrl
    ) {
        URL.revokeObjectURL(
            currentImageUrl,
        );
    }

    currentImageUrl = null;

    imageUrlIsObjectUrl = false;
};

const renderImage = (): void => {
    if (!map) {
        return;
    }

    clearImage();

    if (!props.mapImage) {
        return;
    }

    /*
     * Los bounds solamente indican
     * dónde debe aparecer el plano.
     */
    const bounds =
        getImageBounds();

    if (!bounds) {
        return;
    }

    const imageUrl =
        resolveImageUrl(
            props.mapImage,
        );

    currentImageUrl =
        imageUrl;

    imageUrlIsObjectUrl =
        typeof props.mapImage !==
        'string';

    imageOverlay =
        L.imageOverlay(
            imageUrl,
            bounds,
            {
                opacity: 0.9,

                /*
                 * La imagen jamás captura
                 * eventos del mouse.
                 */
                interactive: false,

                /*
                 * Debajo del marker.
                 */
                pane: 'zooImagePane',

                className:
                    'map-marker-image-overlay',
            },
        ).addTo(map);
};

/*
|--------------------------------------------------------------------------
| MARKER
|--------------------------------------------------------------------------
*/

const clearMarker = (): void => {
    if (marker) {
        marker.remove();

        marker = null;
    }
};

const createMarker = (
    latitude: number,
    longitude: number,
): void => {
    if (!map) {
        return;
    }

    clearMarker();

    marker =
        L.marker(
            [
                latitude,
                longitude,
            ],
            {
                icon: markerIcon,

                /*
                 * Marker encima de todo.
                 */
                pane: 'zooMarkerPane',

                zIndexOffset: 10000,

                draggable:
                    !props.readonly,

                bubblingMouseEvents:
                    false,
            },
        ).addTo(map);

    /*
     * Al comenzar a arrastrar,
     * bloqueamos el movimiento del mapa.
     */
    marker.on(
        'dragstart',
        () => {
            if (!map) {
                return;
            }

            map.dragging.disable();
        },
    );

    /*
     * Al terminar de arrastrar,
     * comprobamos la ZONA.
     */
    marker.on(
        'dragend',
        () => {
            if (!marker || !map) {
                return;
            }

            map.dragging.enable();

            const position =
                marker.getLatLng();

            /*
             * IMPORTANTE:
             *
             * Solamente validamos contra
             * la geometría de la ZONA.
             *
             * La imagen no importa.
             */
            if (
                !isInsideZone(
                    position.lat,
                    position.lng,
                )
            ) {
                /*
                 * Si intentó salir de la zona,
                 * regresamos a la última posición
                 * válida.
                 */
                if (
                    props.latitude !==
                        null &&
                    props.latitude !==
                        undefined &&
                    props.longitude !==
                        null &&
                    props.longitude !==
                        undefined
                ) {
                    marker.setLatLng([
                        props.latitude,
                        props.longitude,
                    ]);
                }

                return;
            }

            /*
             * Nueva posición válida.
             */
            updateCoordinates(
                position,
            );
        },
    );
};

/*
|--------------------------------------------------------------------------
| RENDER DE ZONA
|--------------------------------------------------------------------------
*/

const renderZone = (): void => {
    if (!map) {
        return;
    }

    /*
     * Eliminar zona anterior.
     */
    if (zoneLayer) {
        zoneLayer.remove();

        zoneLayer = null;
    }

    /*
     * Eliminar marker anterior.
     */
    clearMarker();

    /*
     * Dibujar la geometría de la zona.
     */
    if (props.geometry) {
        zoneLayer =
            L.geoJSON(
                props.geometry as GeoJSON.GeoJsonObject,
                {
                    pane: 'zooZonePane',

                    /*
                     * La geometría no captura clicks.
                     */
                    interactive: false,

                    style: {
                        color: '#2563eb',
                        weight: 3,
                        fillOpacity: 0.08,
                    },
                },
            ).addTo(map);
    }

    /*
     * Dibujar el plano.
     *
     * Esto es independiente de la zona.
     */
    renderImage();

    const zoneBounds =
        zoneLayer?.getBounds();

    /*
     * IMPORTANTE:
     *
     * Centramos el mapa en la ZONA,
     * no en la imagen.
     *
     * Así también podemos ver y utilizar
     * las partes de la zona que están fuera
     * del plano.
     */
    if (
        zoneBounds &&
        zoneBounds.isValid()
    ) {
        map.fitBounds(
            zoneBounds,
            {
                padding: [
                    30,
                    30,
                ],
            },
        );
    } else {
        /*
         * Si no hay zona pero sí imagen,
         * usamos la imagen solamente para
         * posicionar el mapa.
         */
        const imageBounds =
            getImageBounds();

        if (
            imageBounds &&
            imageBounds.isValid()
        ) {
            map.fitBounds(
                imageBounds,
                {
                    padding: [
                        30,
                        30,
                    ],
                },
            );
        } else {
            map.setView(
                defaultCenter,
                15,
            );
        }
    }

    /*
     * Restaurar marker existente.
     */
    if (
        props.latitude !==
            null &&
        props.latitude !==
            undefined &&
        props.longitude !==
            null &&
        props.longitude !==
            undefined
    ) {
        /*
         * El marker existente solamente
         * debe estar dentro de la ZONA.
         */
        if (
            isInsideZone(
                props.latitude,
                props.longitude,
            )
        ) {
            createMarker(
                props.latitude,
                props.longitude,
            );
        }
    }
};

/*
|--------------------------------------------------------------------------
| CLICK SOBRE EL MAPA
|--------------------------------------------------------------------------
*/

const handleMapClick = (
    event: L.LeafletMouseEvent,
): void => {
    if (props.readonly) {
        return;
    }

    const latitude =
        event.latlng.lat;

    const longitude =
        event.latlng.lng;

    /*
     * ==========================================================
     * IMPORTANTE
     * ==========================================================
     *
     * NO usamos:
     *
     *     mapImageBounds
     *
     * para validar el click.
     *
     * Solamente usamos:
     *
     *     geometry
     *
     * Por lo tanto:
     *
     *      ┌────────────────────────┐
     *      │        IMAGEN          │
     *      │       📍               │
     *      │                        │
     *      └────────────────────────┘
     *
     *                  📍
     *
     * El segundo marker también puede
     * colocarse si está dentro de la ZONA,
     * aunque esté fuera de la imagen.
     */

    if (
        !isInsideZone(
            latitude,
            longitude,
        )
    ) {
        return;
    }

    /*
     * Crear marker.
     */
    createMarker(
        latitude,
        longitude,
    );

    /*
     * Actualizar coordenadas del formulario.
     */
    updateCoordinates(
        event.latlng,
    );
};

/*
|--------------------------------------------------------------------------
| MOUNTED
|--------------------------------------------------------------------------
*/

onMounted(() => {
    if (!mapElement.value) {
        return;
    }

    map =
        L.map(
            mapElement.value,
            {
                zoomControl: true,
            },
        ).setView(
            defaultCenter,
            15,
        );

    /*
     * Crear panes antes de
     * dibujar las capas.
     */
    createMapPanes();

    /*
     * Mapa base.
     */
    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors',
        },
    ).addTo(map);

    /*
     * Evento click.
     *
     * Como la imagen y la zona tienen
     * pointer-events: none, el click
     * llega directamente al mapa.
     */
    map.on(
        'click',
        handleMapClick,
    );

    /*
     * Dibujar zona, imagen y marker.
     */
    renderZone();

    /*
     * Leaflet necesita recalcular
     * el tamaño después de renderizar.
     */
    setTimeout(() => {
        map?.invalidateSize();
    }, 100);
});

/*
|--------------------------------------------------------------------------
| WATCH GEOMETRY
|--------------------------------------------------------------------------
*/

watch(
    () => props.geometry,
    () => {
        renderZone();
    },
    {
        deep: true,
    },
);

/*
|--------------------------------------------------------------------------
| WATCH IMAGEN
|--------------------------------------------------------------------------
*/

watch(
    () => props.mapImage,
    () => {
        if (!map) {
            return;
        }

        renderImage();
    },
);

/*
|--------------------------------------------------------------------------
| WATCH BOUNDS DE IMAGEN
|--------------------------------------------------------------------------
*/

watch(
    () => props.mapImageBounds,
    () => {
        if (!map) {
            return;
        }

        renderImage();
    },
    {
        deep: true,
    },
);

/*
|--------------------------------------------------------------------------
| WATCH COORDENADAS
|--------------------------------------------------------------------------
*/

watch(
    () => [
        props.latitude,
        props.longitude,
    ],
    ([latitude, longitude]) => {
        if (!map) {
            return;
        }

        /*
         * Sin coordenadas,
         * quitar marker.
         */
        if (
            latitude ===
                null ||
            latitude ===
                undefined ||
            longitude ===
                null ||
            longitude ===
                undefined
        ) {
            clearMarker();

            return;
        }

        /*
         * Las coordenadas también deben
         * respetar la ZONA.
         */
        if (
            !isInsideZone(
                latitude,
                longitude,
            )
        ) {
            return;
        }

        if (marker) {
            marker.setLatLng([
                latitude,
                longitude,
            ]);
        } else {
            createMarker(
                latitude,
                longitude,
            );
        }
    },
);

/*
|--------------------------------------------------------------------------
| UNMOUNTED
|--------------------------------------------------------------------------
*/

onBeforeUnmount(() => {
    clearMarker();

    clearImage();

    if (map) {
        map.remove();

        map = null;
    }
});
</script>

<template>
    <div
        ref="mapElement"
        class="h-[500px] w-full overflow-hidden rounded-lg border border-sidebar-border"
    />
</template>

<style>
.custom-map-marker {
    background: transparent !important;
    border: none !important;

    width: 40px !important;
    height: 40px !important;

    pointer-events: auto !important;
}

.map-marker-pin {
    width: 40px;
    height: 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 36px;
    line-height: 1;

    /*
     * El contenido del pin no captura
     * el evento del mouse.
     */
    pointer-events: none;

    filter:
        drop-shadow(
            0 2px 3px
            rgba(0, 0, 0, 0.55)
        );
}

.map-marker-image-overlay {
    /*
     * El plano nunca bloquea
     * los clicks del mapa.
     */
    pointer-events: none !important;
}
</style>
