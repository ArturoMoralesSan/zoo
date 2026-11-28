<?php

namespace App\Services;

use App\Enums\ConservationStatus;
use App\Enums\Diet;
use App\Enums\Habitat;
use App\Exceptions\SpeciesInfoGenerationException;
use App\Models\SpeciesCategory;
use App\Models\SpeciesTag;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Str;

/**
 * Sugiere los datos de una especie con Gemini a partir de sus nombres.
 *
 * No guarda nada: las sugerencias van al formulario y una persona las revisa,
 * porque la IA puede equivocarse (sobre todo con el estado de conservación,
 * que cambia con el tiempo). Categoría y etiquetas se eligen solo entre las
 * activas: la IA nunca crea registros de catálogo.
 */
class SpeciesInfoGeneratorService
{
    public const FIELDS = [
        'description',
        'origin',
        'diet',
        'habitats',
        'conservation_status',
        'species_category_id',
        'tags',
    ];

    private const MAX_HABITATS = 4;

    private const MAX_TAGS = 6;

    private const MAX_ORIGIN_LENGTH = 255;

    private const MAX_DESCRIPTION_LENGTH = 600;

    public function __construct(private GeminiClient $gemini) {}

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured();
    }

    /**
     * @param  list<string>  $fields
     * @return array{
     *     description?: string,
     *     origin?: string,
     *     diet?: string,
     *     habitats?: list<string>,
     *     conservation_status?: string,
     *     species_category_id?: int,
     *     tags?: list<int>
     * }
     *
     * @throws SpeciesInfoGenerationException
     */
    public function generate(string $commonName, string $scientificName, array $fields): array
    {
        if (! $this->isConfigured()) {
            throw SpeciesInfoGenerationException::notConfigured();
        }

        $fields = array_values(array_intersect(self::FIELDS, $fields));

        // Nombre => id, para que la IA elija por nombre y nunca invente ids.
        $categories = in_array('species_category_id', $fields, true)
            ? SpeciesCategory::query()->where('is_active', true)->orderBy('name')->pluck('id', 'name')->all()
            : [];

        $tags = in_array('tags', $fields, true)
            ? SpeciesTag::query()->where('is_active', true)->orderBy('name')->pluck('id', 'name')->all()
            : [];

        $json = $this->gemini->generateJson(
            $this->buildPrompt($commonName, $scientificName, $fields, array_keys($categories), array_keys($tags)),
            $this->responseSchema($fields, array_keys($categories), array_keys($tags)),
            SpeciesInfoGenerationException::class
        );

        if ($json === null) {
            throw SpeciesInfoGenerationException::emptyResult();
        }

        if (($json['recognized'] ?? null) !== true) {
            throw SpeciesInfoGenerationException::unknownSpecies();
        }

        return $this->normalize($json, $fields, $categories, $tags);
    }

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $categoryNames
     * @param  list<string>  $tagNames
     */
    private function buildPrompt(
        string $commonName,
        string $scientificName,
        array $fields,
        array $categoryNames,
        array $tagNames
    ): string {
        $lines = [
            "Completa la ficha de la especie \"{$commonName}\" (nombre científico: {$scientificName}) para el panel de un zoológico.",
            '',
            'Reglas:',
            '- Identifica la especie por su nombre científico. Si no la conoces, si el nombre científico no existe o si no corresponde al nombre común, responde solo recognized: false.',
            '- Usa solo datos verificables y generales de la especie. Si no estás seguro de un dato, omite ese campo.',
            '- Español neutro y sencillo, para visitantes del zoológico (niños y adultos). Sin markdown.',
        ];

        if (in_array('description', $fields, true)) {
            $lines[] = '- description: un solo párrafo corto, de 200 a 350 caracteres, con lo más interesante de la especie. No menciones el zoológico.';
        }

        if (in_array('origin', $fields, true)) {
            $lines[] = '- origin: región o países de origen de su distribución natural, en pocas palabras (por ejemplo "Centro y Sudamérica").';
        }

        if (in_array('diet', $fields, true)) {
            $lines[] = '- diet: el código que mejor describe su alimentación principal. Códigos: '.$this->codes(Diet::cases()).'.';
        }

        if (in_array('habitats', $fields, true)) {
            $lines[] = '- habitats: de 1 a '.self::MAX_HABITATS.' códigos de los hábitats donde vive en libertad, del más al menos importante. Códigos: '.$this->codes(Habitat::cases()).'.';
        }

        if (in_array('conservation_status', $fields, true)) {
            $lines[] = '- conservation_status: su categoría más reciente en la Lista Roja de la UICN. Usa DOM solo para animales domésticos. Códigos: '.$this->codes(ConservationStatus::cases()).'.';
        }

        if ($categoryNames) {
            $lines[] = '- category: la categoría de la lista que mejor le corresponda. Omítela si ninguna encaja.';
        }

        if ($tagNames) {
            $lines[] = '- tags: hasta '.self::MAX_TAGS.' etiquetas de la lista que le apliquen claramente. Deja la lista vacía si ninguna aplica.';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<Diet|Habitat|ConservationStatus>  $cases
     */
    private function codes(array $cases): string
    {
        return collect($cases)
            ->map(fn (Diet|Habitat|ConservationStatus $case) => "{$case->value} ({$case->label()})")
            ->implode(', ');
    }

    /**
     * Esquema solo con los campos pedidos; los catálogos van como enum para que
     * la IA no pueda devolver texto libre en ellos.
     *
     * @param  list<string>  $fields
     * @param  list<string>  $categoryNames
     * @param  list<string>  $tagNames
     * @return array<string, mixed>
     */
    private function responseSchema(array $fields, array $categoryNames, array $tagNames): array
    {
        $properties = ['recognized' => ['type' => 'boolean']];

        if (in_array('description', $fields, true)) {
            $properties['description'] = ['type' => 'string'];
        }

        if (in_array('origin', $fields, true)) {
            $properties['origin'] = ['type' => 'string'];
        }

        if (in_array('diet', $fields, true)) {
            $properties['diet'] = ['type' => 'string', 'enum' => array_column(Diet::cases(), 'value')];
        }

        if (in_array('habitats', $fields, true)) {
            $properties['habitats'] = [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => array_column(Habitat::cases(), 'value')],
                'maxItems' => self::MAX_HABITATS,
            ];
        }

        if (in_array('conservation_status', $fields, true)) {
            $properties['conservation_status'] = [
                'type' => 'string',
                'enum' => array_column(ConservationStatus::cases(), 'value'),
            ];
        }

        if ($categoryNames) {
            $properties['category'] = ['type' => 'string', 'enum' => $categoryNames];
        }

        if ($tagNames) {
            $properties['tags'] = [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => $tagNames],
                'maxItems' => self::MAX_TAGS,
            ];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => ['recognized'],
        ];
    }

    /**
     * Descarta lo que no sea válido: el esquema guía a la IA, pero no se confía en él.
     *
     * @param  array<mixed>  $json
     * @param  list<string>  $fields
     * @param  array<string, int>  $categories
     * @param  array<string, int>  $tags
     * @return array<string, mixed>
     */
    private function normalize(array $json, array $fields, array $categories, array $tags): array
    {
        $wants = fn (string $field) => in_array($field, $fields, true);
        $text = fn (mixed $value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $suggestions = [];

        if ($wants('description') && ($description = $text($json['description'] ?? null))) {
            $suggestions['description'] = Str::limit($description, self::MAX_DESCRIPTION_LENGTH);
        }

        if ($wants('origin') && ($origin = $text($json['origin'] ?? null))) {
            $suggestions['origin'] = Str::limit($origin, self::MAX_ORIGIN_LENGTH - 3);
        }

        if ($wants('diet') && ($diet = Diet::tryFrom((string) ($json['diet'] ?? '')))) {
            $suggestions['diet'] = $diet->value;
        }

        if ($wants('habitats') && is_array($json['habitats'] ?? null)) {
            $habitats = collect($json['habitats'])
                ->map(fn ($code) => is_string($code) ? Habitat::tryFrom($code)?->value : null)
                ->filter()
                ->unique()
                ->take(self::MAX_HABITATS)
                ->values()
                ->all();

            if ($habitats) {
                $suggestions['habitats'] = $habitats;
            }
        }

        if ($wants('conservation_status')
            && ($status = ConservationStatus::tryFrom((string) ($json['conservation_status'] ?? '')))) {
            $suggestions['conservation_status'] = $status->value;
        }

        if ($wants('species_category_id') && is_string($json['category'] ?? null)
            && isset($categories[$json['category']])) {
            $suggestions['species_category_id'] = $categories[$json['category']];
        }

        if ($wants('tags') && is_array($json['tags'] ?? null)) {
            $suggestions['tags'] = collect($json['tags'])
                ->map(fn ($name) => is_string($name) ? ($tags[$name] ?? null) : null)
                ->filter()
                ->unique()
                ->take(self::MAX_TAGS)
                ->values()
                ->all();
        }

        return $suggestions;
    }
}
