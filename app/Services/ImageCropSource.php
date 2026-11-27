<?php

namespace App\Services;

use App\Support\UploadExtension;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Copia reducida de la foto completa y recuadro del último recorte, para que
 * «Recortar imagen actual» parta de la foto entera y no de la imagen ya
 * recortada y comprimida. Ver «Original para volver a recortar» en
 * docs/image-optimization.md.
 *
 * El formulario envía `<campo>_source` (la copia, solo si la foto es nueva) y
 * `<campo>_crop` (el recuadro en píxeles de esa copia) junto con la imagen.
 */
class ImageCropSource
{
    public const CROP_KEYS = ['x', 'y', 'width', 'height', 'rotate', 'scaleX', 'scaleY', 'aspectRatio'];

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $field): array
    {
        $maxSide = (int) config('images.crop_source.max_side');

        return [
            "{$field}_source" => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.crop_source.max_kb'),
                Rule::dimensions()->maxWidth($maxSide)->maxHeight($maxSide),
            ],
            "{$field}_crop" => ['nullable', 'array:'.implode(',', self::CROP_KEYS), "required_with:{$field}_source"],
            "{$field}_crop.x" => ["required_with:{$field}_crop", 'numeric'],
            "{$field}_crop.y" => ["required_with:{$field}_crop", 'numeric'],
            "{$field}_crop.width" => ["required_with:{$field}_crop", 'numeric', 'gt:0'],
            "{$field}_crop.height" => ["required_with:{$field}_crop", 'numeric', 'gt:0'],
            "{$field}_crop.rotate" => ['nullable', 'numeric', 'between:-360,360'],
            "{$field}_crop.scaleX" => ['nullable', 'numeric', 'in:-1,1'],
            "{$field}_crop.scaleY" => ['nullable', 'numeric', 'in:-1,1'],
            "{$field}_crop.aspectRatio" => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(string $field, string $label): array
    {
        return [
            "{$field}_source" => "original de la {$label}",
            "{$field}_crop" => "recorte de la {$label}",
        ];
    }

    /**
     * Original y recorte tras subir una imagen nueva al campo.
     *
     * - Con copia nueva: se guarda y reemplaza la anterior.
     * - Solo con recorte: se volvió a recortar desde la copia guardada.
     * - Sin nada (p. ej. «Usar sin recortar»): la copia anterior ya no
     *   corresponde a la imagen y se borra.
     *
     * @return array{0: string|null, 1: array<string, float|null>|null}
     */
    public static function store(
        Request $request,
        string $field,
        ?string $currentPath,
        string $directory
    ): array {
        $crop = self::crop($request->input("{$field}_crop"));
        $file = $request->file("{$field}_source");

        if ($file && $file->isValid() && $crop) {
            $path = $file->storeAs(
                trim($directory, '/').'/sources',
                Str::random(40).'.'.UploadExtension::for($file, attribute: "{$field}_source"),
                'public'
            );

            self::delete($currentPath);

            return [$path ?: null, $crop];
        }

        if ($crop && $currentPath) {
            return [$currentPath, $crop];
        }

        self::delete($currentPath);

        return [null, null];
    }

    public static function delete(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return array<string, float|null>|null
     */
    private static function crop(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $crop = [];

        foreach (self::CROP_KEYS as $key) {
            $value = $input[$key] ?? null;
            $crop[$key] = is_numeric($value) ? (float) $value : null;
        }

        return $crop['width'] && $crop['height'] ? $crop : null;
    }
}
