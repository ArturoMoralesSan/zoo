<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Species\StoreSpeciesImageCreditRequest;
use App\Http\Resources\Credits\CreditResource;
use App\Models\Credit;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Créditos de las imágenes de una especie (principal, miniatura y galería).
 *
 * Cada imagen es su propio dueño: tiene un crédito de origen y, si hace
 * falta, créditos de las obras en que se basa y del material que incluye.
 * A diferencia de la tarjeta, una imagen no se hace a partir de otra.
 */
class SpeciesCreditController extends Controller
{
    /**
     * Posición de cada imagen de la galería (id => índice), por petición.
     *
     * @var array<int, int>|null
     */
    private ?array $galleryPositions = null;

    /**
     * Créditos de la especie, por imagen.
     */
    public function index(Species $species): Response
    {
        $images = $this->creditedImages($species);

        return Inertia::render('admin/species-credits/Index', [
            'species' => [
                ...$this->speciesSummary($species),
                'is_active' => $species->is_active,
            ],

            'images' => $images
                ->map(fn (SpeciesImage $image) => $this->imageSummary($image))
                ->values(),

            /*
             * Lo que mostrará la app (GET /api/v1/credits) para esta especie,
             * si está activa: solo las imágenes activas.
             */
            'appPreview' => $images
                ->filter(fn (SpeciesImage $image) => $image->is_active)
                ->flatMap(fn (SpeciesImage $image) => $image->creditsFor(CreditAsset::Image)
                    ->map(fn (Credit $credit) => [
                        ...(new CreditResource($credit))->resolve(),
                        'image_label' => $this->imageLabel($image),
                    ]))
                ->values(),
        ]);
    }

    /**
     * Formulario para crear.
     */
    public function create(Request $request, Species $species, SpeciesImage $image): Response
    {
        return Inertia::render('admin/species-credits/Create', [
            ...$this->formProps($species, $image),

            // Tipo y origen elegidos desde la lista (?role=…&source=…).
            'preset' => [
                'role' => CreditRole::tryFrom((string) $request->query('role'))?->value,
                'source' => AssetSource::tryFrom((string) $request->query('source'))?->value,
            ],
        ]);
    }

    /**
     * Guardar crédito.
     */
    public function store(
        StoreSpeciesImageCreditRequest $request,
        Species $species,
        SpeciesImage $image
    ): RedirectResponse {
        $image->credits()->create($request->creditData());

        $this->flashSuccess('Crédito agregado correctamente.');

        return redirect()->to($this->imageUrl($species, $image));
    }

    /**
     * Formulario para editar.
     */
    public function edit(Species $species, SpeciesImage $image, Credit $credit): Response
    {
        return Inertia::render('admin/species-credits/Edit', [
            ...$this->formProps($species, $image),

            'credit' => [
                ...$credit->only([
                    'id',
                    'license_id',
                    'sort_order',
                    'title',
                    'author_name',
                    'author_url',
                    'source_url',
                    'modified',
                    'modification_note',
                    'attribution_override',
                    'ai_prompt',
                    'notes',
                ]),
                'asset' => $credit->asset->value,
                'role' => $credit->role->value,
                'source_name' => $credit->source_name?->value,
                'based_on_asset' => null,
                'acquired_at' => $credit->acquired_at?->format('Y-m-d'),
                'ai_checked_at' => $credit->ai_checked_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Actualizar crédito.
     */
    public function update(
        StoreSpeciesImageCreditRequest $request,
        Species $species,
        SpeciesImage $image,
        Credit $credit
    ): RedirectResponse {
        $credit->update($request->creditData());

        $this->flashSuccess('Crédito actualizado correctamente.');

        return redirect()->to($this->imageUrl($species, $image));
    }

    /**
     * Eliminar crédito.
     */
    public function destroy(Species $species, SpeciesImage $image, Credit $credit): RedirectResponse
    {
        $credit->delete();

        $this->flashSuccess('Crédito eliminado correctamente.');

        return redirect()->to($this->imageUrl($species, $image));
    }

    /**
     * Imágenes con créditos (principal, miniatura y galería), en orden.
     *
     * @return Collection<int, SpeciesImage>
     */
    private function creditedImages(Species $species)
    {
        return $species->images()
            ->credited()
            ->with('credits.license')
            ->get()
            ->sortBy(fn (SpeciesImage $image) => $image->creditOrderKey())
            ->values();
    }

    private function imageUrl(Species $species, SpeciesImage $image): string
    {
        return route('admin.species.credits.index', $species).'#image-'.$image->id;
    }

    /**
     * @return array{id: int, common_name: string, scientific_name: string|null}
     */
    private function speciesSummary(Species $species): array
    {
        return $species->only('id', 'common_name', 'scientific_name');
    }

    /**
     * "Imagen principal", "Miniatura", "Galería 3" (posición en la galería).
     */
    private function imageLabel(SpeciesImage $image): string
    {
        if ($image->type !== 'gallery') {
            return $image->typeLabel();
        }

        $this->galleryPositions ??= SpeciesImage::query()
            ->where('species_id', $image->species_id)
            ->where('type', 'gallery')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->flip()
            ->all();

        return 'Galería '.(($this->galleryPositions[$image->id] ?? 0) + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function imageData(SpeciesImage $image): array
    {
        return [
            'id' => $image->id,
            'type' => $image->type,
            'label' => $this->imageLabel($image),
            'alt_text' => $image->alt_text,
            'is_active' => $image->is_active,
            'preview_url' => $image->path ? Storage::disk('public')->url($image->path) : null,
            'has_file' => $image->hasAsset(CreditAsset::Image),
            'has_origin' => $image->credit(CreditAsset::Image) !== null,
        ];
    }

    /**
     * Datos comunes de los formularios de crear y editar.
     *
     * @return array<string, mixed>
     */
    private function formProps(Species $species, SpeciesImage $image): array
    {
        $image->load('credits.license');

        $licenses = License::query()->ordered()->get();

        return [
            'species' => $this->speciesSummary($species),

            'image' => $this->imageData($image),

            'licenses' => $licenses->map->toFormOption(),

            // Licencias que admite cada origen (solo el recurso "image").
            'assetSources' => AssetSource::formOptions($licenses, [CreditAsset::Image]),

            'allowRestrictedLicenses' => (bool) config('licensing.allow_restricted'),
        ];
    }

    /**
     * Resumen de una imagen para la lista: estado, créditos y avisos.
     *
     * @return array<string, mixed>
     */
    private function imageSummary(SpeciesImage $image): array
    {
        return [
            ...$this->imageData($image),
            'status' => match (true) {
                ! $image->hasAsset(CreditAsset::Image) => 'no_file',
                $image->hasCompleteCredits(CreditAsset::Image) => 'complete',
                default => 'missing',
            },
            'credits' => $image->creditsFor(CreditAsset::Image)->map(fn (Credit $credit) => [
                'id' => $credit->id,
                'role' => $credit->role->value,
                'role_label' => $credit->role->label(),
                'source_label' => $credit->source_name?->label(),
                'source_url' => $credit->source_url,
                'license_short_name' => $credit->license->short_name,
                'license_url' => $credit->license->url,
                'attribution' => $credit->attribution,
                'is_ai' => $credit->isAiGenerated(),
                'ai_checked_at' => $credit->ai_checked_at?->toIso8601String(),
            ]),
            'alerts' => $this->alerts($image),
        ];
    }

    /**
     * Avisos de licencias de una imagen.
     *
     * @return array<int, array{type: string, message: string}>
     */
    private function alerts(SpeciesImage $image): array
    {
        $alerts = [];
        $origin = $image->credit(CreditAsset::Image);

        if ($origin?->isAiGenerated()) {
            $alerts[] = $origin->ai_checked_at
                ? [
                    'type' => 'info',
                    'message' => 'Imagen generada con IA: probablemente no tiene derechos de autor propios, así que otros podrían copiarla. Los retoques hechos a mano sí se protegen.',
                ]
                : [
                    'type' => 'warning',
                    'message' => 'Imagen generada con IA sin revisar. Edita su crédito y confirma la revisión.',
                ];
        }

        foreach ($image->creditsFor(CreditAsset::Image) as $credit) {
            if ($credit->role !== CreditRole::Origin && $credit->license->share_alike) {
                $alerts[] = ['type' => 'warning', 'message' => "Usa material {$credit->license->short_name}: la imagen resultante debe compartirse con la misma licencia."];
            }
        }

        return $alerts;
    }

    /**
     * Mensaje de éxito (toast) para la siguiente página.
     */
    private function flashSuccess(string $message): void
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }
}
