<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | Detrás de un balanceador o Cloudflare todas las peticiones llegan con la
    | IP del proxy, y los límites por IP (créditos, login, registro) se
    | comparten entre todos los usuarios. Aquí van las IP o rangos del proxy
    | separados por comas, o "*" si el servidor solo es accesible a través de
    | él. Vacío = no se confía en ningún proxy.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
