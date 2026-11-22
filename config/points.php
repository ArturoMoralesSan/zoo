<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monto mínimo de donación para otorgar puntos
    |--------------------------------------------------------------------------
    |
    | En MXN. Una donación menor se registra igual, pero no da puntos.
    |
    */

    'donation_min_amount' => (float) env('POINTS_DONATION_MIN_AMOUNT', 50),

    /*
    |--------------------------------------------------------------------------
    | Reglas de puntos en modo anterior
    |--------------------------------------------------------------------------
    |
    | Con true, las reglas se muestran como antes: el panel no marca qué
    | reglas tienen efecto ("Lo otorga", "Asignada a", "Sin uso") ni avisa al
    | crear una, y GET /api/points/season lista en `other` todas las reglas
    | activas que cambia la temporada, aunque ninguna acción las otorgue.
    | No cambia cómo se otorgan los puntos.
    |
    */

    'rules_legacy' => (bool) env('APP_POINTS_RULES_LEGACY', false),

];
