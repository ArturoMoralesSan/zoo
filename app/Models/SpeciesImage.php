<?php

namespace App\Models;

use App\Contracts\Creditable;
use App\Enums\CreditAsset;
use App\Models\Concerns\HasCredits;
use App\Observers\CreditsCacheObserver;
use App\Services\ImageCropSource;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Imagen de una especie. Las de tipo principal, miniatura y galería llevan
 * créditos propios (recurso CreditAsset::Image); las de tipo "card" no, porque
 * la app no las muestra.
 */
#[ObservedBy(CreditsCacheObserver::class)]
class SpeciesImage extends Model implements Creditable
{
    use HasCredits;

    /**
     * Tipos de imagen que la app muestra y que llevan créditos, en orden.
     */
    public const CREDITED_TYPES = ['main', 'thumbnail', 'gallery'];

    /**
     * Tipos con punto focal: la app los recorta en marcos de distinta
     * proporción. La miniatura ya se recorta 1:1 al subirla.
     */
    public const FOCAL_TYPES = ['main', 'gallery'];

    protected $fillable = [
        'species_id',
        'type',
        'path',
        'source_path',
        'crop',
        'original_bytes',
        'alt_text',
        'focal_x',
        'focal_y',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'original_bytes' => 'integer',
            'focal_x' => 'float',
            'focal_y' => 'float',
            'crop' => 'array',
        ];
    }

    protected static function booted(): void
    {
        /*
         * La relación polimórfica no tiene llave foránea: los créditos se
         * borran aquí, uno por uno, para que CreditsCacheObserver se entere.
         */
        static::deleting(function (SpeciesImage $image) {
            $image->credits()->get()->each->delete();
        });

        // El original para volver a recortar no sirve sin su imagen. Tras el
        // commit: si la transacción se deshace, la fila vuelve con su archivo.
        static::deleted(fn (SpeciesImage $image) => DB::afterCommit(
            fn () => ImageCropSource::delete($image->source_path)
        ));
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @param  Builder<SpeciesImage>  $query
     * @return Builder<SpeciesImage>
     */
    public function scopeCredited(Builder $query): Builder
    {
        return $query->whereIn('type', self::CREDITED_TYPES);
    }

    /**
     * Punto focal como { x, y } (0 a 1), o null si se usa el centro.
     *
     * @return array{x: float, y: float}|null
     */
    public function focal(): ?array
    {
        if ($this->focal_x === null || $this->focal_y === null) {
            return null;
        }

        return ['x' => $this->focal_x, 'y' => $this->focal_y];
    }

    public function isCredited(): bool
    {
        return in_array($this->type, self::CREDITED_TYPES, true);
    }

    public function hasAsset(CreditAsset $asset): bool
    {
        return $asset === CreditAsset::Image && $this->isCredited() && filled($this->path);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'main' => 'Imagen principal',
            'thumbnail' => 'Miniatura',
            'gallery' => 'Galería',
            default => ucfirst((string) $this->type),
        };
    }

    /**
     * Orden en los listados: principal, miniatura y galería por posición.
     */
    public function creditOrderKey(): string
    {
        $type = array_search($this->type, self::CREDITED_TYPES, true);

        return sprintf('%d-%05d-%010d', $type === false ? 9 : $type, $this->sort_order, $this->id);
    }

    /**
     * Le falta el crédito de origen.
     */
    public function missingCredits(): bool
    {
        return $this->hasAsset(CreditAsset::Image) && ! $this->hasCompleteCredits(CreditAsset::Image);
    }
}
