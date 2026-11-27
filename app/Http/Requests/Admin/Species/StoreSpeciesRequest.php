<?php

namespace App\Http\Requests\Admin\Species;

use App\Concerns\ImageAspectRules;
use App\Concerns\SpeciesModelValidationRules;
use App\Enums\ConservationStatus;
use App\Enums\Diet;
use App\Enums\Habitat;
use App\Services\ImageCropSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpeciesRequest extends FormRequest
{
    use ImageAspectRules;
    use SpeciesModelValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('species.create') ?? false;
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

            'habitats' => [
                'nullable',
                'array',
            ],

            'habitats.*' => [
                'distinct',
                Rule::enum(Habitat::class),
            ],

            'origin' => [
                'nullable',
                'string',
                'max:255',
            ],

            'diet' => [
                'nullable',
                Rule::enum(Diet::class),
            ],

            'conservation_status' => [
                'nullable',
                Rule::enum(ConservationStatus::class),
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
                $this->aspectRule('species', 'thumbnail'),
            ],

            // Copia de la foto completa y recuadro, para volver a recortar.
            ...ImageCropSource::rules('main'),

            /*
             * Punto focal de la imagen principal (0 a 1, desde la esquina
             * superior izquierda). La app recorta con cover y lo mantiene a la
             * vista. Sin enviarlo se conserva el guardado.
             */
            'main_focal_x' => [
                'nullable',
                'numeric',
                'between:0,1',
                'required_with:main_focal_y',
            ],

            'main_focal_y' => [
                'nullable',
                'numeric',
                'between:0,1',
                'required_with:main_focal_x',
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
             * Orden final de la galería: cada elemento apunta a un archivo
             * nuevo por su índice en gallery_images.
             */
            /*
             * Peso de cada imagen antes de que el navegador la comprimiera,
             * para mostrar el ahorro. gallery_original_bytes va por índice
             * de gallery_images.
             */
            'image_original_bytes' => [
                'nullable',
                'array:main,thumbnail',
            ],

            'image_original_bytes.*' => [
                'nullable',
                'integer',
                'min:1',
                'max:104857600',
            ],

            'gallery_original_bytes' => [
                'nullable',
                'array',
            ],

            'gallery_original_bytes.*' => [
                'nullable',
                'integer',
                'min:1',
                'max:104857600',
            ],

            'gallery' => [
                'nullable',
                'array',
            ],

            'gallery.*.file_index' => [
                'required',
                'integer',
                'min:0',
            ],

            'gallery.*.alt_text' => [
                'nullable',
                'string',
                'max:255',
            ],

            'gallery.*.focal_x' => [
                'nullable',
                'numeric',
                'between:0,1',
                'required_with:gallery.*.focal_y',
            ],

            'gallery.*.focal_y' => [
                'nullable',
                'numeric',
                'between:0,1',
                'required_with:gallery.*.focal_x',
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

            ...$this->modelAndCardImageRules(),
        ];
    }

    public function messages(): array
    {
        return $this->aspectMessages('species', 'thumbnail_image', 'thumbnail');
    }

    public function attributes(): array
    {
        return [
            'species_category_id' => 'categoría',
            'common_name' => 'nombre común',
            'scientific_name' => 'nombre científico',
            'description' => 'descripción',
            'habitats' => 'hábitats',
            'habitats.*' => 'hábitat',
            'origin' => 'origen',
            'diet' => 'dieta',
            'conservation_status' => 'estado de conservación',
            'is_active' => 'estado',

            'tags' => 'etiquetas',

            'main_image' => 'imagen principal',
            ...ImageCropSource::attributes('main', 'imagen principal'),
            'thumbnail_image' => 'miniatura',
            'card_image' => 'imagen de tarjeta',
            'gallery_images' => 'imágenes de galería',
            'gallery_images.*' => 'imagen de galería',
            'gallery.*.alt_text' => 'texto alternativo',
            'main_focal_x' => 'punto focal (horizontal)',
            'main_focal_y' => 'punto focal (vertical)',
            'gallery.*.focal_x' => 'punto focal (horizontal)',
            'gallery.*.focal_y' => 'punto focal (vertical)',

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
