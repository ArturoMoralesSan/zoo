<?php

/*
 * Optimización de imágenes (WebP). Ver docs/image-optimization.md.
 */
return [

    /*
     * Codificador del servidor: auto (Imagick y luego GD), imagick, gd o none.
     * Sin uno disponible, el navegador comprime antes de subir.
     */
    'driver' => env('IMAGE_ENCODER', 'auto'),

    /*
     * Perfiles por imagen de la tarjeta, compartidos con el navegador
     * (resources/data/card-image-profiles.json). Nunca se agranda una imagen.
     * - generated: 2× la plantilla (572 × 1016), igual que la textura del visor 3D.
     * - artwork: alimenta futuros renders de la tarjeta, conserva más resolución.
     */
    'cards' => json_decode(
        file_get_contents(resource_path('data/card-image-profiles.json')),
        true,
        flags: JSON_THROW_ON_ERROR
    ),

    /*
     * Perfiles por tipo de imagen de especie, compartidos con el navegador
     * (resources/data/species-image-profiles.json).
     * - main y gallery: pantalla completa del teléfono.
     * - thumbnail: listas y marcador del mapa, se muestra pequeña.
     */
    'species' => json_decode(
        file_get_contents(resource_path('data/species-image-profiles.json')),
        true,
        flags: JSON_THROW_ON_ERROR
    ),

    /*
     * Copia de la foto completa que se guarda para volver a recortar la
     * ilustración de la tarjeta y la imagen principal de la especie. La reduce
     * el navegador (lib/imageCrop.ts, CROP_SOURCE_PROFILE) al mismo lado máximo.
     */
    'crop_source' => [
        'max_side' => 2400,
        'max_kb' => 8192,
    ],

];
