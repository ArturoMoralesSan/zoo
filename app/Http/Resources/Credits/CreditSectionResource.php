<?php

namespace App\Http\Resources\Credits;

use App\Models\Card;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Sección de la pantalla de créditos: una tarjeta y sus créditos.
 *
 * @mixin Card
 */
class CreditSectionResource extends JsonResource
{
    /**
     * @param  Card  $resource
     * @param  Collection<int, Credit>  $credits  Créditos de la tarjeta, ya ordenados.
     */
    public function __construct(
        $resource,
        private Collection $credits
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?Request $request = null): array
    {
        return [
            'card' => [
                'id' => $this->id,
                'name' => $this->name,
                'species_name' => $this->species?->common_name,
            ],

            'credits' => $this->credits
                ->map(fn (Credit $credit) => (new CreditResource($credit))->resolve($request))
                ->all(),
        ];
    }
}
