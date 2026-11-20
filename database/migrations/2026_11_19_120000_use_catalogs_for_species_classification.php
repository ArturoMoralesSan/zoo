<?php

use App\Enums\ConservationStatus;
use App\Enums\Diet;
use App\Enums\Habitat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Hábitat, dieta y estado de conservación pasan de texto libre a catálogos.
 * Se guardan códigos estables (los de los enums) y el hábitat admite varios
 * valores, por eso se mueve a la columna JSON `habitats`.
 *
 * El texto existente se convierte sin distinguir acentos, mayúsculas ni
 * género ("Carnivora" → carnivore). Lo que no se reconoce queda vacío y se
 * registra en el log para revisarlo a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->json('habitats')->nullable()->after('description');
        });

        $unmatched = [];

        DB::table('species')
            ->select('id', 'habitat', 'diet', 'conservation_status')
            ->orderBy('id')
            ->each(function (object $row) use (&$unmatched) {
                $status = $this->matchConservationStatus($row->conservation_status);
                $diet = $this->matchDiet($row->diet);
                [$habitats, $leftover] = $this->matchHabitats($row->habitat);

                foreach ([
                    'conservation_status' => $row->conservation_status !== null && trim($row->conservation_status) !== '' && $status === null ? $row->conservation_status : null,
                    'diet' => $row->diet !== null && trim($row->diet) !== '' && $diet === null ? $row->diet : null,
                    'habitat' => $leftover === [] ? null : implode(', ', $leftover),
                ] as $column => $value) {
                    if ($value !== null) {
                        $unmatched[] = "species #{$row->id} {$column}: \"{$value}\"";
                    }
                }

                DB::table('species')->where('id', $row->id)->update([
                    'conservation_status' => $status,
                    'diet' => $diet,
                    'habitats' => $habitats === [] ? null : json_encode($habitats),
                ]);
            });

        if ($unmatched !== []) {
            Log::warning('Especies: valores sin equivalente en los catálogos, quedaron vacíos.', $unmatched);
        }

        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn('habitat');
        });
    }

    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->string('habitat')->nullable()->after('description');
        });

        DB::table('species')
            ->select('id', 'habitats', 'diet', 'conservation_status')
            ->orderBy('id')
            ->each(function (object $row) {
                $codes = json_decode($row->habitats ?? '[]', true);

                $habitats = collect(is_array($codes) ? $codes : [])
                    ->map(fn ($code) => Habitat::tryFrom($code)?->label())
                    ->filter()
                    ->implode(', ');

                DB::table('species')->where('id', $row->id)->update([
                    'habitat' => $habitats !== '' ? $habitats : null,
                    'diet' => $row->diet ? (Diet::tryFrom($row->diet)?->label() ?? $row->diet) : null,
                    'conservation_status' => $row->conservation_status
                        ? (ConservationStatus::tryFrom($row->conservation_status)?->label() ?? $row->conservation_status)
                        : null,
                ]);
            });

        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn('habitats');
        });
    }

    private function normalize(?string $value): string
    {
        return Str::of((string) $value)->trim()->lower()->ascii()->squish()->toString();
    }

    private function matchConservationStatus(?string $value): ?string
    {
        $text = $this->normalize($value);

        if ($text === '') {
            return null;
        }

        foreach (ConservationStatus::cases() as $case) {
            if ($text === strtolower($case->value) || $text === $this->normalize($case->label())) {
                return $case->value;
            }
        }

        // Variantes comunes (femenino, "de extinción", sin "en").
        return match (true) {
            str_contains($text, 'silvestre') => ConservationStatus::ExtinctInTheWild->value,
            str_starts_with($text, 'extint') => ConservationStatus::Extinct->value,
            str_contains($text, 'critic') => ConservationStatus::CriticallyEndangered->value,
            str_contains($text, 'casi amenazad') => ConservationStatus::NearThreatened->value,
            str_contains($text, 'peligro') => ConservationStatus::Endangered->value,
            str_contains($text, 'vulnerable') => ConservationStatus::Vulnerable->value,
            str_contains($text, 'preocupacion menor') => ConservationStatus::LeastConcern->value,
            str_contains($text, 'datos insuficientes') => ConservationStatus::DataDeficient->value,
            str_contains($text, 'no evaluad') => ConservationStatus::NotEvaluated->value,
            default => null,
        };
    }

    private function matchDiet(?string $value): ?string
    {
        $text = $this->normalize($value);

        if ($text === '') {
            return null;
        }

        // Por raíz, para aceptar "carnívora", "herbívoros", etc.
        $stems = [
            'carnivor' => Diet::Carnivore,
            'herbivor' => Diet::Herbivore,
            'omnivor' => Diet::Omnivore,
            'insectivor' => Diet::Insectivore,
            'piscivor' => Diet::Piscivore,
            'frugivor' => Diet::Frugivore,
            'nectarivor' => Diet::Nectarivore,
            'granivor' => Diet::Granivore,
            'folivor' => Diet::Folivore,
            'carron' => Diet::Scavenger,
            'planctivor' => Diet::Planktivore,
        ];

        foreach ($stems as $stem => $diet) {
            if (str_starts_with($text, $stem) || $text === $diet->value) {
                return $diet->value;
            }
        }

        return null;
    }

    /**
     * @return array{0: list<string>, 1: list<string>} Códigos reconocidos y partes sin equivalente.
     */
    private function matchHabitats(?string $value): array
    {
        $parts = preg_split('/\s*(?:,|;|\/|\by\b|\be\b)\s*/u', (string) $value) ?: [];

        // El orden importa: lo más específico primero.
        $keywords = [
            'selva seca' => Habitat::DryForest,
            'caducifolia' => Habitat::DryForest,
            'niebla' => Habitat::CloudForest,
            'conifera' => Habitat::ConiferousForest,
            'selva' => Habitat::TropicalRainforest,
            'jungla' => Habitat::TropicalRainforest,
            'bosque' => Habitat::TemperateForest,
            'sabana' => Habitat::Savanna,
            'pastizal' => Habitat::Grassland,
            'pradera' => Habitat::Grassland,
            'zonas abiertas' => Habitat::Grassland,
            'matorral' => Habitat::Scrubland,
            'desierto' => Habitat::Desert,
            'montan' => Habitat::Mountain,
            'tundra' => Habitat::Polar,
            'polar' => Habitat::Polar,
            'humedal' => Habitat::Wetland,
            'pantano' => Habitat::Wetland,
            'manglar' => Habitat::Mangrove,
            'rio' => Habitat::Freshwater,
            'lago' => Habitat::Freshwater,
            'agua dulce' => Habitat::Freshwater,
            'costa' => Habitat::Coast,
            'playa' => Habitat::Coast,
            'arrecife' => Habitat::CoralReef,
            'coral' => Habitat::CoralReef,
            'ocean' => Habitat::Ocean,
            'marin' => Habitat::Ocean,
            'cueva' => Habitat::Cave,
            'caverna' => Habitat::Cave,
            'urban' => Habitat::Urban,
            'ciudad' => Habitat::Urban,
        ];

        $found = [];
        $leftover = [];

        foreach ($parts as $part) {
            $text = $this->normalize($part);

            if ($text === '') {
                continue;
            }

            $match = Habitat::tryFrom($text);

            if ($match === null) {
                foreach ($keywords as $keyword => $habitat) {
                    if (str_contains($text, $keyword)) {
                        $match = $habitat;
                        break;
                    }
                }
            }

            if ($match === null) {
                $leftover[] = trim($part);
            } else {
                $found[$match->value] = true;
            }
        }

        // Mismo orden que el catálogo.
        $codes = array_values(array_filter(
            array_map(fn (Habitat $h) => $h->value, Habitat::cases()),
            fn (string $code) => isset($found[$code]),
        ));

        return [$codes, $leftover];
    }
};
