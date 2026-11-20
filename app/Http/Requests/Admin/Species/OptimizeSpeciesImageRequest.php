<?php

namespace App\Http\Requests\Admin\Species;

use App\Http\Requests\Admin\Card\OptimizeCardImageRequest;

/**
 * Mismas reglas que las tarjetas: ruta esperada y, opcionalmente, el WebP
 * codificado en el navegador.
 */
class OptimizeSpeciesImageRequest extends OptimizeCardImageRequest {}
