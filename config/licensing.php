<?php

/*
|--------------------------------------------------------------------------
| Licencias y créditos de los recursos de las tarjetas
|--------------------------------------------------------------------------
|
| Catálogo de licencias (LicenseSeeder), créditos por recurso (asset_credits)
| y la API pública de créditos. Ver docs/api-credits.md.
|
*/

return [

    /*
     * Permite elegir licencias restringidas (NC, ND, GFDL…). Por defecto no:
     * el juego es comercial y las tarjetas modifican la imagen.
     */
    'allow_restricted' => (bool) env('LICENSING_ALLOW_RESTRICTED', false),

    /*
     * Consulta de metadatos en Wikimedia Commons.
     */
    'commons' => [
        'api_url' => 'https://commons.wikimedia.org/w/api.php',

        // Segundos de espera máxima por consulta.
        'timeout' => 5,

        // Segundos que se guarda cada respuesta (1 día).
        'cache_ttl' => 86400,

        /*
         * Wikimedia pide un User-Agent descriptivo con un contacto.
         */
        'contact' => env('LICENSING_CONTACT', env('MAIL_FROM_ADDRESS')),
    ],

    /*
     * API pública de créditos (GET /api/v1/credits).
     */
    'api' => [
        // Peticiones por minuto por IP.
        'rate_limit' => 60,

        // Segundos que el cliente puede reutilizar la respuesta.
        'max_age' => 300,
    ],

];
