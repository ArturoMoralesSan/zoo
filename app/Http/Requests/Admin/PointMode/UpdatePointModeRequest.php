<?php

namespace App\Http\Requests\Admin\PointMode;

/**
 * Mismas reglas que al crear; el nombre único ignora el modo editado.
 */
class UpdatePointModeRequest extends StorePointModeRequest {}
