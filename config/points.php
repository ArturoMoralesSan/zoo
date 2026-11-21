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

];
