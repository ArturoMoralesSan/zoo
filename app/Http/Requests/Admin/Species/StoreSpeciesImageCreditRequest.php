<?php

namespace App\Http\Requests\Admin\Species;

use App\Http\Requests\Admin\Card\StoreCardCreditRequest;

/**
 * Validación para crear o editar un crédito de una imagen de especie
 * (principal, miniatura o galería). Mismas reglas que los de la tarjeta;
 * el recurso siempre es "image" y no se hace a partir de otro.
 */
class StoreSpeciesImageCreditRequest extends StoreCardCreditRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->creditRules(
            $this->route('image'),
            $this->route('credit')
        );
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            $this->creditChecks($this->route('image'), $this->route('credit')),
        ];
    }
}
