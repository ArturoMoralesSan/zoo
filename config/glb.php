<?php

/*
|--------------------------------------------------------------------------
| Modelos 3D (GLB) de las tarjetas
|--------------------------------------------------------------------------
|
| El navegador escala el modelo a su tamaño real (1 unidad = 1 m) y el
| servidor valida el archivo escalado antes de guardarlo. Ver
| docs/glb-resizing.md.
|
*/

return [

    /*
     * Tamaño máximo de cada archivo (original y escalado), en KB.
     */
    'max_file_size' => (int) env('GLB_MAX_FILE_SIZE', 51200),

    /*
     * Alto final permitido, en metros.
     */
    'min_height' => 0.01,
    'max_height' => 100,

    /*
     * Diferencia máxima entre el alto pedido y el del archivo (1 %).
     */
    'height_tolerance' => 0.01,

    /*
     * Distancia máxima del origen al centro inferior del modelo:
     * el mayor entre el valor absoluto (m) y el relativo a su tamaño.
     */
    'origin_tolerance' => [
        'absolute' => 0.001,
        'relative' => 0.001,
    ],

    /*
     * Tamaño máximo del bloque JSON del GLB, en bytes.
     */
    'max_json_chunk_size' => 8 * 1024 * 1024,

    /*
     * Discos: el archivo escalado es el que descarga el juego (público);
     * el original solo se usa para volver a escalar (privado).
     */
    'disks' => [
        'resized' => 'public',
        'original' => 'local',
    ],

    /*
     * Extensiones de glTF que ViroReact muestra correctamente. Cualquier otra
     * solo produce una advertencia (nunca bloquea el guardado).
     *
     * IMPORTANTE: esta lista es una suposición inicial. Ajústala después de
     * probar modelos reales en ViroReact.
     */
    'supported_extensions' => [
        'KHR_materials_unlit',
        'KHR_texture_transform',
        'KHR_materials_emissive_strength',
    ],

];
