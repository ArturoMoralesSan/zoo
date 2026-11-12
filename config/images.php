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

];
