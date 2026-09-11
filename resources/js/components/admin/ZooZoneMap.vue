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

interface MapImageBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

const props = withDefaults(
    defineProps<{
        modelValue?: PolygonGeometry | null;

        /*
         * Puede ser:
         *
         * File   -> imagen nueva seleccionada
         * string -> imagen existente almacenada
         */
        mapImage?: File | string | null;

        mapImageBounds?: MapImageBounds | null;

        center?: [number, number];

        zoom?: number;

        readonly?: boolean;

        height?: string;
    }>(),
    {
        modelValue: null,
        mapImage: null,
        mapImageBounds: null,
        center: () => [24.03, -104.67],
        zoom: 16,
        readonly: false,
        height: '600px',
    },
);

const emit = defineEmits<{
    (
        event: 'update:modelValue',
        value: PolygonGeometry | null,
    ): void;

    (
        event: 'update:mapImageBounds',
        value: MapImageBounds | null,
    ): void;
}>();

const mapElement = ref<HTMLElement | null>(null);

const isDrawing = ref(false);

const drawingPoints = ref<
    [number, number][]
>([]);

const hasImage = ref(false);

let map: L.Map | null = null;

let drawnItems: L.FeatureGroup | null = null;

let drawControl: L.Control.Draw | null = null;

let drawingLayer: L.Polygon | null = null;

let drawingMarkers: L.CircleMarker[] = [];

let imageOverlay: L.ImageOverlay | null = null;

let imageRectangle: L.Rectangle | null = null;

let imageCenterMarker: L.Marker | null = null;

let imageCornerMarkers: L.CircleMarker[] = [];

/*
 * URL utilizada actualmente por Leaflet.
 */
let imageUrl: string | null = null;

/*
 * Indica si imageUrl fue creada con
 * URL.createObjectURL().
 *
 * Solo esas URLs deben revocarse.
 */
let imageUrlIsObjectUrl = false;

let syncingFromParent = false;

let updatingImage = false;

/*
|--------------------------------------------------------------------------
| Utilidades del mapa
|--------------------------------------------------------------------------
*/

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
        coordinates:
            geoJson.geometry.coordinates,
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

    const layer =
        getCurrentLayer();

    if (!layer) {
        emit(
            'update:modelValue',
            null,
        );

        return;
    }

    emit(
        'update:modelValue',
        layerToGeometry(layer),
    );
};

/*
|--------------------------------------------------------------------------
| Dibujar zona
|--------------------------------------------------------------------------
*/

const clearLayers = () => {
    drawnItems?.clearLayers();
};

const clearDrawingPreview = () => {
    if (
        drawingLayer &&
        map
    ) {
        map.removeLayer(
            drawingLayer,
        );
    }

    drawingLayer = null;

    drawingMarkers.forEach(
        (marker) => {
            map?.removeLayer(
                marker,
            );
        },
    );

    drawingMarkers = [];
};

const updateDrawingPreview = () => {
    if (!map) {
        return;
    }

    clearDrawingPreview();

    if (!drawingPoints.value.length) {
        return;
    }

    drawingMarkers =
        drawingPoints.value.map(
            ([latitude, longitude]) => {
                const marker =
                    L.circleMarker(
                        [
                            latitude,
                            longitude,
                        ],
                        {
                            radius: 5,
                            weight: 2,
                            fillOpacity: 1,
                        },
                    );

                marker.addTo(
                    map!,
                );

                return marker;
            },
        );

    if (
        drawingPoints.value.length >= 2
    ) {
        const latLngs =
            drawingPoints.value.map(
                (
                    [
                        latitude,
                        longitude,
                    ],
                ) =>
                    [
                        latitude,
                        longitude,
                    ] as L.LatLngExpression,
            );

        drawingLayer =
            L.polygon(
                latLngs,
                {
                    weight: 2,
                    dashArray: '6 6',
                    fillOpacity: 0.08,
                },
            );

        drawingLayer.addTo(
            map,
        );
    }
};

const startDrawing = () => {
    if (
        !map ||
        props.readonly
    ) {
        return;
    }

    clearLayers();

    clearDrawingPreview();

    clearImageEditor();

    drawingPoints.value = [];

    isDrawing.value = true;
};

const cancelDrawing = () => {
    drawingPoints.value = [];

    clearDrawingPreview();

    isDrawing.value = false;
};

const undoLastPoint = () => {
    if (
        !isDrawing.value ||
        !drawingPoints.value.length
    ) {
        return;
    }

    drawingPoints.value.pop();

    updateDrawingPreview();
};

const finishDrawing = () => {
    if (
        !map ||
        !drawnItems ||
        !isDrawing.value
    ) {
        return;
    }

    if (
        drawingPoints.value.length < 3
    ) {
        window.alert(
            'La zona debe tener al menos 3 puntos.',
        );

        return;
    }

    const latLngs =
        drawingPoints.value.map(
            (
                [
                    latitude,
                    longitude,
                ],
            ) =>
                [
                    latitude,
                    longitude,
                ] as L.LatLngExpression,
        );

    const polygon =
        L.polygon(
            latLngs,
            {
                weight: 3,
            },
        );

    drawnItems.addLayer(
        polygon,
    );

    clearDrawingPreview();

    drawingPoints.value = [];

    isDrawing.value = false;

    emitCurrentGeometry();

    if (props.mapImage) {
        createInitialImageBounds();

        renderImage();
    }
};

const handleMapClick = (
    event: L.LeafletMouseEvent,
) => {
    if (
        props.readonly ||
        !isDrawing.value
    ) {
        return;
    }

    drawingPoints.value.push([
        event.latlng.lat,
        event.latlng.lng,
    ]);

    updateDrawingPreview();
};

const loadGeometry = () => {
    if (
        !map ||
        !drawnItems
    ) {
        return;
    }

    syncingFromParent = true;

    clearLayers();

    if (
        isDrawing.value
    ) {
        cancelDrawing();
    }

    if (props.modelValue) {
        const layer =
            geometryToLayer(
                props.modelValue,
            );

        if (layer) {
            drawnItems.addLayer(
                layer,
            );

            map.fitBounds(
                layer.getBounds(),
                {
                    padding: [
                        30,
                        30,
                    ],
                },
            );
        }
    }

    syncingFromParent = false;

    if (props.mapImage) {
        renderImage();
    }
};

/*
|--------------------------------------------------------------------------
| Imagen
|--------------------------------------------------------------------------
*/

/*
 * Convierte la imagen recibida en una URL
 * que pueda utilizar Leaflet.
 *
 * File:
 *     crea ObjectURL temporal.
 *
 * string:
 *     utiliza la imagen existente.
 */
const resolveImageUrl = (
    image: File | string,
): string => {
    if (
        typeof image === 'string'
    ) {
        /*
         * Si ya es una URL completa.
         */
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
            image.startsWith(
                '/',
            )
        ) {
            return image;
        }

        /*
         * Las imágenes guardadas por ZooZone
         * se almacenan como:
         *
         * zones/archivo.png
         *
         * y son accesibles mediante:
         *
         * /storage/zones/archivo.png
         */
        return `/storage/${image}`;
    }

    imageUrlIsObjectUrl = true;

    return URL.createObjectURL(
        image,
    );
};

const setImageUrl = (
    image: File | string,
) => {
    /*
     * Si tenemos exactamente la misma
     * URL no necesitamos volver a crearla.
     */
    if (
        typeof image === 'string'
    ) {
        imageUrlIsObjectUrl = false;

        imageUrl =
            resolveImageUrl(
                image,
            );

        return;
    }

    /*
     * File nuevo.
     */
    if (
        imageUrl &&
        imageUrlIsObjectUrl
    ) {
        URL.revokeObjectURL(
            imageUrl,
        );
    }

    imageUrl =
        resolveImageUrl(
            image,
        );
};

const revokeImageUrl = () => {
    if (
        imageUrl &&
        imageUrlIsObjectUrl
    ) {
        URL.revokeObjectURL(
            imageUrl,
        );
    }

    imageUrl = null;

    imageUrlIsObjectUrl = false;
};

const getZoneBounds =
    (): L.LatLngBounds | null => {
        const layer =
            getCurrentLayer();

        if (
            !layer ||
            !(layer instanceof L.Polygon)
        ) {
            return null;
        }

        return layer.getBounds();
    };

const getImageBounds =
    (): L.LatLngBounds | null => {
        if (!imageOverlay) {
            return null;
        }

        return imageOverlay.getBounds();
    };

const emitImageBounds = () => {
    const bounds =
        getImageBounds();

    if (!bounds) {
        emit(
            'update:mapImageBounds',
            null,
        );

        return;
    }

    emit(
        'update:mapImageBounds',
        {
            north:
                bounds.getNorth(),

            south:
                bounds.getSouth(),

            east:
                bounds.getEast(),

            west:
                bounds.getWest(),
        },
    );
};

const createInitialImageBounds = () => {
    if (
        !map ||
        props.mapImageBounds
    ) {
        return;
    }

    const zoneBounds =
        getZoneBounds();

    if (!zoneBounds) {
        return;
    }

    const north =
        zoneBounds.getNorth();

    const south =
        zoneBounds.getSouth();

    const east =
        zoneBounds.getEast();

    const west =
        zoneBounds.getWest();

    const latitudePadding =
        (north - south) * 0.08;

    const longitudePadding =
        (east - west) * 0.08;

    emit(
        'update:mapImageBounds',
        {
            north:
                north -
                latitudePadding,

            south:
                south +
                latitudePadding,

            east:
                east -
                longitudePadding,

            west:
                west +
                longitudePadding,
        },
    );
};

const clearImageEditor = () => {
    if (!map) {
        return;
    }

    if (imageOverlay) {
        map.removeLayer(
            imageOverlay,
        );

        imageOverlay = null;
    }

    if (imageRectangle) {
        map.removeLayer(
            imageRectangle,
        );

        imageRectangle = null;
    }

    if (imageCenterMarker) {
        map.removeLayer(
            imageCenterMarker,
        );

        imageCenterMarker = null;
    }

    imageCornerMarkers.forEach(
        (marker) => {
            map?.removeLayer(
                marker,
            );
        },
    );

    imageCornerMarkers = [];

    hasImage.value = false;
};

const updateImageOverlay = (
    bounds: L.LatLngBounds,
) => {
    if (!imageOverlay) {
        return;
    }

    imageOverlay.setBounds(
        bounds,
    );

    imageRectangle?.setBounds(
        bounds,
    );
};

/*
|--------------------------------------------------------------------------
| Bloqueo del movimiento del mapa
|--------------------------------------------------------------------------
*/

const disableMapDragging = () => {
    if (!map) {
        return;
    }

    map.dragging.disable();
};

const enableMapDragging = () => {
    if (
        !map ||
        props.readonly
    ) {
        return;
    }

    map.dragging.enable();
};

/*
|--------------------------------------------------------------------------
| Handles de las esquinas
|--------------------------------------------------------------------------
*/

const createCornerMarker = (
    latLng: L.LatLng,
    index: number,
) => {
    const marker =
        L.circleMarker(
            latLng,
            {
                radius: 8,
                weight: 2,
                fillOpacity: 1,
                className:
                    'zoo-image-corner-handle',
            },
        );

    marker.on(
        'mousedown',
        (
            event: L.LeafletMouseEvent,
        ) => {
            if (
                props.readonly ||
                !imageOverlay ||
                !map
            ) {
                return;
            }

            L.DomEvent.stopPropagation(
                event,
            );

            L.DomEvent.preventDefault(
                event,
            );

            disableMapDragging();

            const mapInstance =
                map;

            const moveHandler = (
                moveEvent: L.LeafletMouseEvent,
            ) => {
                if (!imageOverlay) {
                    return;
                }

                const currentBounds =
                    imageOverlay.getBounds();

                let north =
                    currentBounds.getNorth();

                let south =
                    currentBounds.getSouth();

                let east =
                    currentBounds.getEast();

                let west =
                    currentBounds.getWest();

                const lat =
                    moveEvent.latlng.lat;

                const lng =
                    moveEvent.latlng.lng;

                /*
                 * 0 = superior izquierda
                 * 1 = superior derecha
                 * 2 = inferior izquierda
                 * 3 = inferior derecha
                 */
                if (index === 0) {
                    north = lat;
                    west = lng;
                }

                if (index === 1) {
                    north = lat;
                    east = lng;
                }

                if (index === 2) {
                    south = lat;
                    west = lng;
                }

                if (index === 3) {
                    south = lat;
                    east = lng;
                }

                const minimumSize =
                    0.000001;

                if (
                    north <=
                    south +
                        minimumSize
                ) {
                    return;
                }

                if (
                    east <=
                    west +
                        minimumSize
                ) {
                    return;
                }

                updatingImage = true;

                const newBounds =
                    L.latLngBounds(
                        [
                            south,
                            west,
                        ],
                        [
                            north,
                            east,
                        ],
                    );

                updateImageOverlay(
                    newBounds,
                );

                updateImageHandles();

                updatingImage = false;

                emitImageBounds();
            };

            const upHandler = () => {
                mapInstance.off(
                    'mousemove',
                    moveHandler,
                );

                mapInstance.off(
                    'mouseup',
                    upHandler,
                );

                enableMapDragging();
            };

            mapInstance.on(
                'mousemove',
                moveHandler,
            );

            mapInstance.on(
                'mouseup',
                upHandler,
            );
        },
    );

    return marker;
};

const updateImageHandles = () => {
    if (
        !imageOverlay ||
        !map
    ) {
        return;
    }

    const bounds =
        imageOverlay.getBounds();

    const corners: L.LatLng[] = [
        L.latLng(
            bounds.getNorth(),
            bounds.getWest(),
        ),

        L.latLng(
            bounds.getNorth(),
            bounds.getEast(),
        ),

        L.latLng(
            bounds.getSouth(),
            bounds.getWest(),
        ),

        L.latLng(
            bounds.getSouth(),
            bounds.getEast(),
        ),
    ];

    if (
        imageCornerMarkers.length !== 4
    ) {
        imageCornerMarkers.forEach(
            (marker) => {
                map?.removeLayer(
                    marker,
                );
            },
        );

        imageCornerMarkers =
            corners.map(
                (
                    corner,
                    index,
                ) =>
                    createCornerMarker(
                        corner,
                        index,
                    ),
            );

        imageCornerMarkers.forEach(
            (marker) => {
                marker.addTo(
                    map!,
                );
            },
        );
    } else {
        imageCornerMarkers.forEach(
            (
                marker,
                index,
            ) => {
                marker.setLatLng(
                    corners[index],
                );
            },
        );
    }

    if (imageCenterMarker) {
        imageCenterMarker.setLatLng(
            bounds.getCenter(),
        );
    }
};

/*
|--------------------------------------------------------------------------
| Mover imagen
|--------------------------------------------------------------------------
*/

const moveImage = (
    event: L.LeafletMouseEvent,
) => {
    if (
        props.readonly ||
        !imageOverlay ||
        !map
    ) {
        return;
    }

    L.DomEvent.stopPropagation(
        event,
    );

    L.DomEvent.preventDefault(
        event,
    );

    disableMapDragging();

    const mapInstance =
        map;

    const startMouse =
        event.latlng;

    const startBounds =
        imageOverlay.getBounds();

    const startSouth =
        startBounds.getSouth();

    const startNorth =
        startBounds.getNorth();

    const startWest =
        startBounds.getWest();

    const startEast =
        startBounds.getEast();

    const moveHandler = (
        moveEvent: L.LeafletMouseEvent,
    ) => {
        if (!imageOverlay) {
            return;
        }

        const deltaLat =
            moveEvent.latlng.lat -
            startMouse.lat;

        const deltaLng =
            moveEvent.latlng.lng -
            startMouse.lng;

        const newBounds =
            L.latLngBounds(
                [
                    startSouth +
                        deltaLat,

                    startWest +
                        deltaLng,
                ],
                [
                    startNorth +
                        deltaLat,

                    startEast +
                        deltaLng,
                ],
            );

        updatingImage = true;

        updateImageOverlay(
            newBounds,
        );

        updateImageHandles();

        updatingImage = false;

        emitImageBounds();
    };

    const upHandler = () => {
        mapInstance.off(
            'mousemove',
            moveHandler,
        );

        mapInstance.off(
            'mouseup',
            upHandler,
        );

        enableMapDragging();
    };

    mapInstance.on(
        'mousemove',
        moveHandler,
    );

    mapInstance.on(
        'mouseup',
        upHandler,
    );
};

/*
|--------------------------------------------------------------------------
| Crear editor de imagen
|--------------------------------------------------------------------------
*/

const createImageEditor = (
    bounds: L.LatLngBounds,
) => {
    if (
        !map ||
        !imageUrl
    ) {
        return;
    }

    clearImageEditor();

    imageOverlay =
        L.imageOverlay(
            imageUrl,
            bounds,
            {
                opacity: 0.72,
                interactive: true,
                className:
                    'zoo-map-image-overlay',
            },
        );

    imageOverlay.addTo(
        map,
    );

    imageOverlay.on(
        'mousedown',
        moveImage,
    );

    imageRectangle =
        L.rectangle(
            bounds,
            {
                weight: 2,
                fillOpacity: 0,
                interactive: false,
                className:
                    'zoo-map-image-border',
            },
        );

    imageRectangle.addTo(
        map,
    );

    imageCenterMarker =
        L.marker(
            bounds.getCenter(),
            {
                interactive: false,

                icon: L.divIcon({
                    className:
                        'zoo-map-image-center',

                    html: `
                        <div
                            style="
                                width: 28px;
                                height: 28px;
                                border-radius: 9999px;
                                background: rgba(0,0,0,.65);
                                border: 2px solid white;
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                color:white;
                                font-size:14px;
                                box-shadow:0 1px 5px rgba(0,0,0,.35);
                                pointer-events:none;
                            "
                        >
                            ↕
                        </div>
                    `,

                    iconSize: [
                        28,
                        28,
                    ],

                    iconAnchor: [
                        14,
                        14,
                    ],
                }),
            },
        );

    imageCenterMarker.addTo(
        map,
    );

    updateImageHandles();

    hasImage.value = true;
};

const renderImage = () => {
    if (
        !map ||
        !props.mapImage
    ) {
        clearImageEditor();

        return;
    }

    /*
     * Aseguramos que imageUrl corresponda
     * al tipo de imagen actual.
     */
    if (
        !imageUrl
    ) {
        setImageUrl(
            props.mapImage,
        );
    }

    /*
     * Si ya tenemos bounds guardados,
     * utilizamos exactamente esos bounds.
     *
     * Esto es lo importante para EDITAR.
     */
    if (
        props.mapImageBounds
    ) {
        const bounds =
            L.latLngBounds(
                [
                    Number(
                        props.mapImageBounds.south,
                    ),
                    Number(
                        props.mapImageBounds.west,
                    ),
                ],
                [
                    Number(
                        props.mapImageBounds.north,
                    ),
                    Number(
                        props.mapImageBounds.east,
                    ),
                ],
            );

        createImageEditor(
            bounds,
        );

        return;
    }

    /*
     * Imagen nueva sin bounds.
     */
    createInitialImageBounds();

    const zoneBounds =
        getZoneBounds();

    if (!zoneBounds) {
        return;
    }

    const south =
        zoneBounds.getSouth();

    const north =
        zoneBounds.getNorth();

    const west =
        zoneBounds.getWest();

    const east =
        zoneBounds.getEast();

    const latPadding =
        (north - south) * 0.08;

    const lngPadding =
        (east - west) * 0.08;

    const bounds =
        L.latLngBounds(
            [
                south +
                    latPadding,

                west +
                    lngPadding,
            ],
            [
                north -
                    latPadding,

                east -
                    lngPadding,
            ],
        );

    createImageEditor(
        bounds,
    );
};

const removeMapImage = () => {
    clearImageEditor();

    revokeImageUrl();

    emit(
        'update:mapImageBounds',
        null,
    );
};

/*
|--------------------------------------------------------------------------
| Inicialización
|--------------------------------------------------------------------------
*/

const initializeMap = async () => {
    await nextTick();

    if (
        !mapElement.value ||
        map
    ) {
        return;
    }

    map =
        L.map(
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
    ).addTo(
        map,
    );

    drawnItems =
        new L.FeatureGroup();

    map.addLayer(
        drawnItems,
    );

    if (!props.readonly) {
        drawControl =
            new L.Control.Draw({
                position:
                    'topleft',

                edit: {
                    featureGroup:
                        drawnItems,

                    remove: true,
                },

                draw: {
                    polygon: false,
                    polyline: false,
                    rectangle: false,
                    circle: false,
                    circlemarker: false,
                    marker: false,
                },
            });

        map.addControl(
            drawControl,
        );

        map.on(
            L.Draw.Event.EDITED,
            () => {
                emitCurrentGeometry();

                if (
                    props.mapImage
                ) {
                    renderImage();
                }
            },
        );

        map.on(
            L.Draw.Event.DELETED,
            () => {
                emitCurrentGeometry();

                clearImageEditor();

                emit(
                    'update:mapImageBounds',
                    null,
                );
            },
        );

        map.on(
            'click',
            handleMapClick,
        );
    }

    loadGeometry();

    /*
     * Si existe una imagen desde el inicio
     * y loadGeometry todavía no pudo renderizarla,
     * intentamos renderizarla nuevamente.
     */
    if (props.mapImage) {
        if (!imageUrl) {
            setImageUrl(
                props.mapImage,
            );
        }

        renderImage();
    }

    setTimeout(
        () => {
            map?.invalidateSize();
        },
        100,
    );
};

/*
|--------------------------------------------------------------------------
| Watcher geometría
|--------------------------------------------------------------------------
*/

watch(
    () => props.modelValue,
    (
        newGeometry,
        oldGeometry,
    ) => {
        if (
            JSON.stringify(
                newGeometry,
            ) ===
            JSON.stringify(
                oldGeometry,
            )
        ) {
            return;
        }

        if (
            !map ||
            !drawnItems
        ) {
            return;
        }

        loadGeometry();
    },
    {
        deep: true,
    },
);

/*
|--------------------------------------------------------------------------
| Watcher imagen
|--------------------------------------------------------------------------
*/

watch(
    () => props.mapImage,
    (
        newImage,
        oldImage,
    ) => {
        if (
            newImage === oldImage
        ) {
            return;
        }

        if (!newImage) {
            removeMapImage();

            return;
        }

        /*
         * Limpiamos solamente el editor.
         * No eliminamos los bounds aquí porque
         * Edit puede estar utilizando bounds existentes.
         */
        clearImageEditor();

        revokeImageUrl();

        setImageUrl(
            newImage,
        );

        if (map) {
            renderImage();
        }
    },
);

/*
|--------------------------------------------------------------------------
| Watcher bounds
|--------------------------------------------------------------------------
*/

watch(
    () => props.mapImageBounds,
    (
        newBounds,
        oldBounds,
    ) => {
        if (
            updatingImage ||
            JSON.stringify(
                newBounds,
            ) ===
                JSON.stringify(
                    oldBounds,
                )
        ) {
            return;
        }

        if (
            !newBounds ||
            !props.mapImage ||
            !map
        ) {
            return;
        }

        /*
         * Si todavía no tenemos la URL,
         * la creamos.
         */
        if (!imageUrl) {
            setImageUrl(
                props.mapImage,
            );
        }

        const bounds =
            L.latLngBounds(
                [
                    Number(
                        newBounds.south,
                    ),
                    Number(
                        newBounds.west,
                    ),
                ],
                [
                    Number(
                        newBounds.north,
                    ),
                    Number(
                        newBounds.east,
                    ),
                ],
            );

        createImageEditor(
            bounds,
        );
    },
    {
        deep: true,
    },
);

/*
|--------------------------------------------------------------------------
| Ciclo de vida
|--------------------------------------------------------------------------
*/

onMounted(() => {
    initializeMap();
});

onBeforeUnmount(() => {
    clearDrawingPreview();

    clearImageEditor();

    revokeImageUrl();

    if (map) {
        map.dragging.enable();

        map.remove();

        map = null;
    }

    drawnItems = null;

    drawControl = null;
});
</script>

<template>
    <div class="space-y-3">
        <!-- MAPA -->
        <div
            ref="mapElement"
            class="w-full overflow-hidden rounded-lg border border-sidebar-border"
            :style="{ height }"
        />

        <!-- CONTROLES DE ZONA -->
        <div
            v-if="!readonly"
            class="rounded-lg border border-sidebar-border bg-background p-4"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm font-medium">
                        Dibujar zona
                    </p>

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Agrega los puntos directamente sobre el mapa.
                        Puedes colocar tantos puntos como necesites.
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2"
                >
                    <button
                        type="button"
                        :disabled="isDrawing"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="startDrawing"
                    >
                        Nueva zona
                    </button>

                    <button
                        v-if="isDrawing"
                        type="button"
                        :disabled="drawingPoints.length === 0"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                        @click="undoLastPoint"
                    >
                        Deshacer punto
                    </button>

                    <button
                        v-if="isDrawing"
                        type="button"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                        @click="cancelDrawing"
                    >
                        Cancelar
                    </button>

                    <button
                        v-if="isDrawing"
                        type="button"
                        :disabled="drawingPoints.length < 3"
                        class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="finishDrawing"
                    >
                        Cerrar zona
                    </button>
                </div>
            </div>

            <div
                v-if="isDrawing"
                class="mt-3 rounded-lg bg-muted/30 p-3"
            >
                <p class="text-xs text-muted-foreground">
                    Puntos colocados:
                    <span class="font-semibold text-foreground">
                        {{ drawingPoints.length }}
                    </span>
                </p>

                <p
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Haz clic en el mapa para agregar cada punto.
                    Cuando termines, selecciona
                    <strong>Cerrar zona</strong>.
                </p>
            </div>

            <div
                v-else
                class="mt-3 rounded-lg bg-muted/30 p-3"
            >
                <p class="text-xs text-muted-foreground">
                    Puedes dibujar la zona con todos los puntos
                    necesarios. Después podrás editar sus vértices
                    con la herramienta de edición.
                </p>
            </div>
        </div>

        <!-- INFORMACIÓN DEL EDITOR DE IMAGEN -->
        <div
            v-if="!readonly && hasImage"
            class="rounded-lg border border-sidebar-border bg-background p-4"
        >
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm font-medium">
                        Posicionar plano
                    </p>

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Arrastra el plano para moverlo. Arrastra las
                        esquinas para cambiar su tamaño.
                    </p>
                </div>

                <div
                    class="rounded-lg bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
                >
                    Coordenadas automáticas
                </div>
            </div>
        </div>

        <!-- SOLO VISTA -->
        <div
            v-if="readonly"
            class="rounded-lg border border-sidebar-border bg-background p-3"
        >
            <p class="text-sm text-muted-foreground">
                Vista de la zona en el mapa.
            </p>
        </div>
    </div>
</template>
