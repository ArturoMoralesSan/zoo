<?php

namespace App\Http\Requests\Admin\Species;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpeciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('species.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Información
            |--------------------------------------------------------------------------
            */

            'species_category_id' => [
                'required',
                'integer',
                Rule::exists('species_categories', 'id')
                    ->where('is_active', true),
            ],

            'common_name' => [
                'required',
                'string',
                'max:255',
            ],

            'scientific_name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'habitat' => [
                'nullable',
                'string',
                'max:255',
            ],

            'origin' => [
                'nullable',
                'string',
                'max:255',
            ],

            'diet' => [
                'nullable',
                'string',
                'max:255',
            ],

            'conservation_status' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Etiquetas
            |--------------------------------------------------------------------------
            */

            'tags' => [
                'nullable',
                'array',
            ],

            'tags.*' => [
                'integer',
                Rule::exists('species_tags', 'id')
                    ->where('is_active', true),
            ],

            /*
            |--------------------------------------------------------------------------
            | Imágenes
            |--------------------------------------------------------------------------
            */

            'main_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'thumbnail_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'card_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'gallery_images' => [
                'nullable',
                'array',
            ],

            'gallery_images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
            |--------------------------------------------------------------------------
            | Modelo 3D
            |--------------------------------------------------------------------------
            */

            'model_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'model_file' => [
                'nullable',
                'file',
                'mimes:glb,gltf,usdz',
                'max:51200',
            ],

            'model_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'model_format' => [
                'nullable',
                'string',
                'max:50',
            ],

            'model_description' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Ubicación
            |--------------------------------------------------------------------------
            */

            'zone_id' => [
                'nullable',
                'integer',
                Rule::exists('zoo_zones', 'id')
                    ->where('is_active', true),
            ],

            'location_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'location_description' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'species_category_id' => 'categoría',
            'common_name' => 'nombre común',
            'scientific_name' => 'nombre científico',
            'description' => 'descripción',
            'habitat' => 'hábitat',
            'origin' => 'origen',
            'diet' => 'dieta',
            'conservation_status' => 'estado de conservación',
            'is_active' => 'estado',

            'tags' => 'etiquetas',

            'main_image' => 'imagen principal',
            'thumbnail_image' => 'miniatura',
            'card_image' => 'imagen de tarjeta',
            'gallery_images' => 'imágenes de galería',

            'model_name' => 'nombre del modelo 3D',
            'model_file' => 'archivo del modelo 3D',
            'model_url' => 'URL del modelo 3D',
            'model_format' => 'formato del modelo 3D',
            'model_description' => 'descripción del modelo 3D',

            'zone_id' => 'zona',
            'location_name' => 'nombre de la ubicación',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'location_description' => 'descripción de la ubicación',
        ];
    }
}
