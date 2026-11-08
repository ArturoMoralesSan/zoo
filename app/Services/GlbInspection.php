<?php

namespace App\Services;

/**
 * Resultado de validar un GLB escalado.
 *
 * Las medidas son null cuando no se pudieron verificar exactamente
 * (modelos con piel o con mallas rotadas en ángulos no rectos); en ese
 * caso se valida solo la estructura y se agrega una advertencia.
 */
final readonly class GlbInspection
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public ?float $height,
        public ?float $width,
        public ?float $depth,
        public bool $isSkinned,
        public array $warnings = [],
    ) {}

    public function isVerified(): bool
    {
        return $this->height !== null;
    }
}
