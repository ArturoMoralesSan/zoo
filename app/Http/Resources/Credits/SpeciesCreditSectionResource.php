<?php

namespace App\Http\Resources\Credits;

use App\Http\Resources\CardResource;
use App\Models\Credit;
use App\Models\Species;
use App\Models\SpeciesImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Sección de la pantalla de créditos: una especie y los créditos de sus
 * imágenes (principal, miniatura y galería).
 *
 * Cada crédito lleva `image`, la imagen a la que pertenece, para que la app
 * pueda mostrar de qué foto se trata.
 *
 * @mixin Species
 */
class SpeciesCreditSectionResource extends JsonResource
{
    /**
     * @param  Species  $resource
     * @param  Collection<int, Credit>  $credits  Créditos de las imágenes, ya ordenados y con `creditable` cargado.
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
            'type' => 'species',

            'species' => [
                'id' => $this->id,
                'slug' => $this->slug,
                'common_name' => $this->common_name,
                'scientific_name' => $this->scientific_name,
            ],

            'credits' => $this->credits
                ->map(function (Credit $credit) use ($request) {
                    /** @var SpeciesImage $image */
                    $image = $credit->creditable;

                    return [
                        ...(new CreditResource($credit))->resolve($request),
                        'image' => [
                            'id' => $image->id,
                            'type' => $image->type,
                            'type_label' => $image->typeLabel(),
                            'url' => CardResource::publicUrl($image->path),
                            'alt_text' => $image->alt_text ?: null,
                        ],
                    ];
                })
                ->all(),
        ];
    }
}
