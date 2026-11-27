<?php

namespace App\Models;

use App\Enums\ConservationStatus;
use App\Enums\CreditRole;
use App\Enums\Diet;
use App\Enums\Habitat;
use App\Observers\CreditsCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/*
 * El nombre común aparece en la API de créditos (species_name).
 */
/**
 * @property Collection<int, Habitat>|null $habitats
 * @property Diet|null $diet
 * @property ConservationStatus|null $conservation_status
 */
#[ObservedBy(CreditsCacheObserver::class)]
class Species extends Model
{
    protected $fillable = [
        'species_category_id',
        'common_name',
        'scientific_name',
        'slug',
        'description',
        'habitats',
        'origin',
        'diet',
        'conservation_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'habitats' => AsEnumCollection::of(Habitat::class),
            'diet' => Diet::class,
            'conservation_status' => ConservationStatus::class,
        ];
    }

    /**
     * Hábitats en el orden del catálogo, sin importar cómo se eligieron.
     *
     * @return Collection<int, Habitat>
     */
    public function sortedHabitats(): Collection
    {
        $order = array_flip(array_column(Habitat::cases(), 'value'));

        return ($this->habitats ?? collect())
            ->unique()
            ->sortBy(fn (Habitat $habitat) => $order[$habitat->value])
            ->values();
    }

    /**
     * Hábitats como texto ("Desierto, Matorral"). Es lo que la API
     * entregaba antes en `habitat`.
     */
    public function habitatLabel(): ?string
    {
        $labels = $this->sortedHabitats()
            ->map(fn (Habitat $habitat) => $habitat->label())
            ->implode(', ');

        return $labels !== '' ? $labels : null;
    }

    /**
     * @return BelongsTo<SpeciesCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            SpeciesCategory::class,
            'species_category_id'
        );
    }

    /**
     * @return HasMany<SpeciesImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(
            SpeciesImage::class
        )->orderBy('sort_order');
    }

    /**
     * Imágenes que la app muestra (principal, miniatura, galería) con archivo
     * y sin crédito de origen.
     *
     * @return HasMany<SpeciesImage, $this>
     */
    public function imagesMissingCredits(): HasMany
    {
        return $this->images()
            ->credited()
            ->where('path', '!=', '')
            ->whereDoesntHave('credits', fn ($query) => $query->where('role', CreditRole::Origin->value));
    }

    /**
     * "1 imagen", "3 imágenes"… o null si no falta nada. Para los avisos del
     * panel: una especie activa muestra esas imágenes sin sus créditos.
     */
    public function missingCreditsText(): ?string
    {
        $count = $this->imagesMissingCredits()->count();

        return match ($count) {
            0 => null,
            1 => '1 imagen',
            default => "{$count} imágenes",
        };
    }

    public function models(): HasMany
    {
        return $this->hasMany(
            SpeciesModel::class
        );
    }

    /**
     * @return HasMany<SpeciesLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(
            SpeciesLocation::class
        );
    }

    /**
     * @return BelongsToMany<SpeciesTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            SpeciesTag::class,
            'species_tag'
        );
    }

    public function cards()
    {
        return $this->hasMany(
            Card::class
        );
    }
}
