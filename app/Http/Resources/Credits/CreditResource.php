<?php

namespace App\Http\Resources\Credits;

use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila de la pantalla de créditos.
 *
 * Nunca incluye notas, fecha de obtención, indicaciones ni revisión de la IA,
 * rutas de archivos ni el tipo del recurso dueño.
 *
 * @mixin Credit
 */
class CreditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(?Request $request = null): array
    {
        return [
            'id' => $this->id,
            'asset' => $this->asset->value,
            'asset_label' => $this->asset->label(),
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'title' => $this->title ?: null,
            'author' => self::objectOrNull([
                'name' => $this->author_name ?: null,
                'url' => $this->author_url ?: null,
            ]),
            'source' => self::objectOrNull([
                'name' => $this->source_name?->value,
                'label' => $this->source_name?->label(),
                'url' => $this->source_url ?: null,
            ]),
            'license' => [
                'short_name' => $this->license->short_name,
                'name' => $this->license->name,
                'url' => $this->license->url,
            ],
            'modified' => $this->modified,
            'modification_note' => $this->modified ? ($this->modification_note ?: null) : null,
            'attribution' => $this->attribution,

            /*
             * Otro recurso de la tarjeta del que sale este (render del modelo,
             * modelo de la ilustración). Ese recurso tiene sus propias filas;
             * la línea `attribution` ya lo dice.
             */
            'based_on_asset' => $this->based_on_asset?->value,
        ];
    }

    /**
     * Un objeto sin ningún dato se envía como null.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private static function objectOrNull(array $values): ?array
    {
        return array_filter($values, fn ($value) => $value !== null) ? $values : null;
    }
}
