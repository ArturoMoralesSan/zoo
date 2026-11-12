<?php

/*
 * Tarjetas de ZooDeck. Ver docs/offline-packs-backend.md.
 */
return [

    /*
     * Paquetes sin conexión: copias cifradas (AES-256-GCM) de los archivos de
     * cada tarjeta, que la app descarga en Wi-Fi antes de la visita.
     */
    'packs' => [

        /*
         * Desactivado no se escriben ni se borran archivos .bin. Las pruebas lo
         * apagan en phpunit.xml para no tocar storage/ real.
         */
        'enabled' => (bool) env('CARD_PACKS_ENABLED', true),

        /*
         * Secreto con el que se derivan las llaves. Sin él se deriva de APP_KEY.
         * Si cambia, hay que correr `php artisan cards:packs:build --force`.
         */
        'secret' => env('CARD_PACK_SECRET'),

        'disk' => 'public',

        'directory' => 'packs/cards',

    ],

];
