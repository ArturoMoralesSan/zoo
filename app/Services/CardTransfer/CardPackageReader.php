<?php

namespace App\Services\CardTransfer;

use App\Enums\AssetSource;
use App\Enums\CardRarity;
use App\Enums\ConservationStatus;
use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Enums\Diet;
use App\Enums\Habitat;
use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\License;
use App\Models\PointRule;
use App\Models\SpeciesCategory;
use App\Models\SpeciesTag;
use App\Models\ZooZone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use ZipArchive;

/**
 * Lee y valida un paquete de tarjetas sin escribir nada.
 *
 * Rechaza el paquete entero si su estructura no es confiable (nombres fuera
 * del formato, tamaños o hashes que no coinciden, archivos que no son lo que
 * dicen ser). Una tarjeta con datos inválidos solo se marca y se omite.
 */
class CardPackageReader
{
    private const MAGIC = [
        'webp' => ['RIFF', 0],
        'png' => ["\x89PNG", 0],
        'jpg' => ["\xFF\xD8\xFF", 0],
        'jpeg' => ["\xFF\xD8\xFF", 0],
        'glb' => ['glTF', 0],
    ];

    private ZipArchive $zip;

    /** @var array<string, mixed> */
    private array $manifest;

    /**
     * Tarjetas del paquete por uuid: datos ya validados o errores.
     *
     * @var array<string, array{data: array<string, mixed>, errors: list<string>}>|null
     */
    private ?array $cards = null;

    /**
     * Especies del paquete por slug (versión 2): datos ya validados o errores.
     *
     * @var array<string, array{data: array<string, mixed>, errors: list<string>}>|null
     */
    private ?array $species = null;

    private function __construct(ZipArchive $zip)
    {
        $this->zip = $zip;
    }

    public static function open(string $absolutePath): self
    {
        $zip = new ZipArchive;

        if (! is_file($absolutePath) || $zip->open($absolutePath, ZipArchive::RDONLY) !== true) {
            throw new InvalidCardPackageException('El archivo no es un .zip válido.');
        }

        $reader = new self($zip);
        $reader->checkStructure();

        return $reader;
    }

    public function close(): void
    {
        @$this->zip->close();
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return $this->manifest;
    }

    public function includesQrTokens(): bool
    {
        return (bool) ($this->manifest['include_qr_tokens'] ?? false);
    }

    /**
     * El paquete trae la ubicación de las especies (se pidió al exportar).
     */
    public function includesLocations(): bool
    {
        return (bool) ($this->manifest['include_locations'] ?? false);
    }

    public function version(): int
    {
        return (int) ($this->manifest['version'] ?? 0);
    }

    /**
     * Nombres, tamaños y límites. No lee el contenido de los archivos.
     */
    private function checkStructure(): void
    {
        $maxEntries = (int) config('cards.transfer.max_entries');

        if ($this->zip->numFiles > $maxEntries) {
            throw new InvalidCardPackageException("El paquete tiene más de {$maxEntries} archivos.");
        }

        $sizes = [];

        for ($index = 0; $index < $this->zip->numFiles; $index++) {
            $stat = $this->zip->statIndex($index);

            if ($stat === false) {
                throw new InvalidCardPackageException('El .zip está dañado.');
            }

            $name = $stat['name'];

            if (str_ends_with($name, '/')) {
                continue;
            }

            if (! preg_match(CardPackage::ENTRY_PATTERN, $name)) {
                throw new InvalidCardPackageException("El paquete contiene un archivo no permitido: {$name}");
            }

            $sizes[$name] = $stat['size'];
        }

        if (! isset($sizes[CardPackage::MANIFEST])) {
            throw new InvalidCardPackageException('El paquete no tiene manifest.json. ¿Es un paquete exportado desde ZooDeck?');
        }

        $total = array_sum($sizes);

        if ($total > (int) config('cards.transfer.max_uncompressed_bytes')) {
            throw new InvalidCardPackageException('El paquete descomprimido es demasiado grande.');
        }

        if ($sizes[CardPackage::MANIFEST] > 50 * 1024 * 1024) {
            throw new InvalidCardPackageException('El manifest.json es demasiado grande.');
        }

        $manifest = json_decode((string) $this->zip->getFromName(CardPackage::MANIFEST), true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== CardPackage::FORMAT) {
            throw new InvalidCardPackageException('El manifest.json no es de un paquete de tarjetas de ZooDeck.');
        }

        if (! in_array((int) ($manifest['version'] ?? 0), CardPackage::SUPPORTED_VERSIONS, true)) {
            throw new InvalidCardPackageException(
                'El paquete usa la versión '.((int) ($manifest['version'] ?? 0)).' del formato; este servidor lee hasta la versión '.CardPackage::VERSION.'.'
            );
        }

        $files = $manifest['files'] ?? null;

        if (! is_array($files)) {
            throw new InvalidCardPackageException('El manifest.json no lista los archivos del paquete.');
        }

        unset($sizes[CardPackage::MANIFEST]);

        if (array_diff_key($sizes, $files) || array_diff_key($files, $sizes)) {
            throw new InvalidCardPackageException('Los archivos del paquete no coinciden con su manifest.json.');
        }

        foreach ($files as $name => $file) {
            if (! is_array($file) || (int) ($file['bytes'] ?? -1) !== $sizes[$name] || ! is_string($file['sha256'] ?? null)) {
                throw new InvalidCardPackageException("El tamaño de {$name} no coincide con el manifest.json.");
            }
        }

        $this->manifest = $manifest;
    }

    /**
     * Revisa el hash y el tipo real de cada archivo (lee todo el paquete).
     */
    public function verifyFiles(): void
    {
        foreach ($this->manifest['files'] as $name => $file) {
            $stream = $this->zip->getStream($name);

            if (! $stream) {
                throw new InvalidCardPackageException("No se pudo leer {$name}.");
            }

            $context = hash_init('sha256');
            $head = '';

            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);

                if ($chunk === false) {
                    break;
                }

                if (strlen($head) < 16) {
                    $head .= substr($chunk, 0, 16 - strlen($head));
                }

                hash_update($context, $chunk);
            }

            fclose($stream);

            if (! hash_equals((string) $file['sha256'], hash_final($context))) {
                throw new InvalidCardPackageException("{$name} está dañado (su hash no coincide).");
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (isset(self::MAGIC[$extension])) {
                [$magic, $offset] = self::MAGIC[$extension];

                if (substr($head, $offset, strlen($magic)) !== $magic
                    || ($extension === 'webp' && substr($head, 8, 4) !== 'WEBP')) {
                    throw new InvalidCardPackageException("{$name} no es un archivo .{$extension} válido.");
                }
            }
        }
    }

    /**
     * @return resource
     */
    public function stream(string $entry)
    {
        $stream = $this->zip->getStream($entry);

        if (! $stream) {
            throw new InvalidCardPackageException("No se pudo leer {$entry}.");
        }

        return $stream;
    }

    /**
     * @return array<mixed>
     */
    public function json(string $entry, mixed $default = []): mixed
    {
        if (! isset($this->manifest['files'][$entry])) {
            return $default;
        }

        $data = json_decode((string) $this->zip->getFromName($entry), true);

        if (! is_array($data)) {
            throw new InvalidCardPackageException("{$entry} no es un JSON válido.");
        }

        return $data;
    }

    /**
     * @return array<string, array{data: array<string, mixed>, errors: list<string>}>
     */
    public function cards(): array
    {
        if ($this->cards !== null) {
            return $this->cards;
        }

        $this->cards = [];

        foreach (array_keys($this->manifest['files']) as $name) {
            // Las claves ya pasaron ENTRY_PATTERN; ninguna es numérica.
            $name = (string) $name;

            if (! preg_match('#^cards/([0-9a-f-]{36})/card\.json$#', $name, $matches)) {
                continue;
            }

            $uuid = $matches[1];
            $data = json_decode((string) $this->zip->getFromName($name), true);

            $this->cards[$uuid] = is_array($data)
                ? ['data' => $data, 'errors' => $this->validateCard($uuid, $data)]
                : ['data' => [], 'errors' => ['card.json no es un JSON válido.']];
        }

        return $this->cards;
    }

    /**
     * @return array<string, array{data: array<string, mixed>, errors: list<string>}>
     */
    public function species(): array
    {
        if ($this->species !== null) {
            return $this->species;
        }

        $this->species = [];

        foreach (array_keys($this->manifest['files']) as $name) {
            $name = (string) $name;

            if (! preg_match('#^species/([a-z0-9-]+)/species\.json$#', $name, $matches)) {
                continue;
            }

            $slug = $matches[1];
            $data = json_decode((string) $this->zip->getFromName($name), true);

            $this->species[$slug] = is_array($data)
                ? ['data' => $data, 'errors' => $this->validateSpecies($slug, $data)]
                : ['data' => [], 'errors' => ['species.json no es un JSON válido.']];
        }

        return $this->species;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function raritySettings(): array
    {
        $rows = $this->json(CardPackage::RARITY_SETTINGS);

        $validator = Validator::make(['rows' => $rows], [
            'rows' => ['array'],
            'rows.*.rarity' => ['required', Rule::enum(CardRarity::class)],
            'rows.*.questions_per_duel' => ['nullable', 'integer', 'min:1', 'max:50'],
            'rows.*.retries' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rows.*.cooldown_minutes' => ['nullable', 'integer', 'min:0'],
            'rows.*.reveal_answers_on_lockout' => ['nullable', 'boolean'],
            'rows.*.point_rule_type' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new InvalidCardPackageException('La configuración por rareza del paquete no es válida.');
        }

        return array_values($rows);
    }

    /**
     * @return array{win_message?: ?string, retry_message?: ?string, locked_message?: ?string}
     */
    public function duelMessages(): array
    {
        $messages = $this->json(CardPackage::DUEL_MESSAGES);

        return collect(['win_message', 'retry_message', 'locked_message'])
            ->mapWithKeys(fn (string $field) => [
                $field => is_string($messages[$field] ?? null) ? mb_substr($messages[$field], 0, 255) : null,
            ])
            ->all();
    }

    /**
     * Licencias del paquete por slug.
     *
     * @return array<string, array<string, mixed>>
     */
    public function licenses(): array
    {
        return collect($this->json(CardPackage::LICENSES))
            ->filter(fn ($license) => is_array($license) && is_string($license['slug'] ?? null) && is_string($license['name'] ?? null))
            ->mapWithKeys(fn (array $license) => [
                $license['slug'] => array_intersect_key($license, array_flip(CardPackage::LICENSE_FIELDS)),
            ])
            ->all();
    }

    /**
     * Vista previa para el panel: qué se crearía, qué ya existe y qué falta.
     *
     * @return array<string, mixed>
     */
    public function analyze(SpeciesResolver $resolver): array
    {
        $cards = $this->cards();
        $existing = Card::query()
            ->whereIn('uuid', array_keys($cards))
            ->pluck('id', 'uuid');

        $rows = [];
        $missingSpecies = [];
        $licenseSlugs = [];
        $tokens = [];

        // Especies del paquete que se crearán o ya existen: sus tarjetas no
        // necesitan que la persona elija especie.
        $packageSpecies = [];

        foreach ($this->species() as $slug => $item) {
            if (! $item['errors']) {
                $packageSpecies['slug:'.$slug] = true;
                $packageSpecies['name:'.SpeciesResolver::normalize((string) ($item['data']['scientific_name'] ?? ''))] = true;
            }
        }

        foreach ($cards as $uuid => $card) {
            $data = $card['data'];
            $species = (array) ($data['species'] ?? []);
            $match = $card['errors'] ? ['species_id' => null, 'match' => null] : $resolver->resolve($species);

            if (! $card['errors'] && ! $match['species_id'] && (
                isset($packageSpecies['slug:'.($species['slug'] ?? '')])
                || isset($packageSpecies['name:'.SpeciesResolver::normalize((string) ($species['scientific_name'] ?? ''))])
            )) {
                $match = ['species_id' => null, 'match' => 'package'];
            }

            $status = match (true) {
                (bool) $card['errors'] => 'invalid',
                isset($existing[$uuid]) => 'existing',
                default => 'new',
            };

            if (! $card['errors'] && ! $match['species_id'] && $match['match'] !== 'package') {
                $key = SpeciesResolver::key($species);
                $missingSpecies[$key] ??= [
                    'key' => $key,
                    'slug' => $species['slug'] ?? null,
                    'scientific_name' => $species['scientific_name'] ?? null,
                    'common_name' => $species['common_name'] ?? null,
                    'cards' => 0,
                ];
                $missingSpecies[$key]['cards']++;
            }

            foreach ($data['credits'] ?? [] as $credit) {
                if (is_string($credit['license_slug'] ?? null)) {
                    $licenseSlugs[$credit['license_slug']] = true;
                }
            }

            foreach ($data['qr_codes'] ?? [] as $qr) {
                if (is_string($qr['token'] ?? null)) {
                    $tokens[$qr['token']] = $uuid;
                }
            }

            $rows[] = [
                'uuid' => $uuid,
                'name' => $data['attributes']['name'] ?? '(sin nombre)',
                'rarity' => $data['attributes']['rarity'] ?? null,
                'status' => $status,
                'existing_id' => $existing[$uuid] ?? null,
                'species' => [
                    'key' => SpeciesResolver::key($species),
                    'common_name' => $species['common_name'] ?? null,
                    'scientific_name' => $species['scientific_name'] ?? null,
                    'match' => $match['match'],
                ],
                'questions' => count($data['questions'] ?? []),
                'qr_codes' => count($data['qr_codes'] ?? []),
                'files' => count(array_filter((array) ($data['files'] ?? []))),
                'errors' => $card['errors'],
            ];
        }

        foreach ($this->species() as $species) {
            foreach (self::speciesImageCredits($species['data']) as $credit) {
                if (is_string($credit['license_slug'] ?? null)) {
                    $licenseSlugs[$credit['license_slug']] = true;
                }
            }
        }

        $packageLicenses = $this->licenses();
        $knownLicenses = License::query()
            ->whereIn('slug', array_keys($licenseSlugs))
            ->pluck('is_selectable', 'slug');

        $ruleTypes = collect($this->raritySettings())->pluck('point_rule_type')->filter()->unique();
        $knownRules = PointRule::query()->whereIn('type', $ruleTypes)->pluck('type');

        // Tokens del paquete que este servidor ya usa en otra tarjeta.
        $tokenConflicts = 0;

        if ($tokens) {
            $owners = CardQrCode::query()
                ->whereIn('token', array_keys($tokens))
                ->with('card:id,uuid')
                ->get(['id', 'card_id', 'token']);

            foreach ($owners as $qr) {
                if ($qr->card?->uuid !== $tokens[$qr->token]) {
                    $tokenConflicts++;
                }
            }
        }

        $counts = collect($rows)->countBy('status');

        return [
            'manifest' => [
                'app' => $this->manifest['app'] ?? null,
                'source_url' => $this->manifest['source_url'] ?? null,
                'exported_at' => $this->manifest['exported_at'] ?? null,
                'version' => $this->version(),
                'include_qr_tokens' => $this->includesQrTokens(),
                'include_locations' => $this->includesLocations(),
                'total_bytes' => (int) ($this->manifest['total_bytes'] ?? 0),
            ],
            'species' => $this->analyzeSpecies($resolver),
            'counts' => [
                'total' => count($rows),
                'new' => $counts->get('new', 0),
                'existing' => $counts->get('existing', 0),
                'invalid' => $counts->get('invalid', 0),
            ],
            'cards' => $rows,
            'missing_species' => array_values($missingSpecies),
            'licenses' => [
                // Se crearán desactivadas para elegirse en el panel.
                'missing' => array_values(array_filter(
                    array_keys($licenseSlugs),
                    fn (string $slug) => ! $knownLicenses->has($slug)
                )),
                'unknown' => array_values(array_filter(
                    array_keys($licenseSlugs),
                    fn (string $slug) => ! $knownLicenses->has($slug) && ! isset($packageLicenses[$slug])
                )),
                'not_selectable' => $knownLicenses->filter(fn ($selectable) => ! $selectable)->keys()->values()->all(),
            ],
            'missing_point_rules' => $ruleTypes->diff($knownRules)->values()->all(),
            'qr_token_conflicts' => $tokenConflicts,
        ];
    }

    /**
     * Especies del paquete y lo que falta en este servidor para importarlas:
     * categorías, etiquetas y zonas que la persona decide crear, asignar u
     * omitir.
     *
     * @return array<string, mixed>
     */
    private function analyzeSpecies(SpeciesResolver $resolver): array
    {
        $rows = [];
        $categories = [];
        $tags = [];
        $zones = [];

        $knownCategories = SpeciesCategory::query()->pluck('name')
            ->mapWithKeys(fn (string $name) => [CardPackage::nameKey($name) => true]);
        $knownTags = SpeciesTag::query()->get(['name', 'slug'])
            ->flatMap(fn (SpeciesTag $tag) => [CardPackage::nameKey($tag->name) => true, 'slug:'.$tag->slug => true]);
        $knownZones = ZooZone::query()->pluck('name')
            ->mapWithKeys(fn (string $name) => [CardPackage::nameKey($name) => true]);

        foreach ($this->species() as $slug => $item) {
            $data = $item['data'];
            $match = $item['errors']
                ? ['species_id' => null, 'match' => null]
                : $resolver->resolve(['slug' => $slug, 'scientific_name' => $data['scientific_name'] ?? null]);

            $rows[] = [
                'slug' => $slug,
                'common_name' => $data['common_name'] ?? $slug,
                'scientific_name' => $data['scientific_name'] ?? null,
                'status' => match (true) {
                    (bool) $item['errors'] => 'invalid',
                    $match['species_id'] !== null => 'existing',
                    default => 'new',
                },
                'existing_id' => $match['species_id'],
                'match' => $match['match'],
                'images' => (int) isset($data['images']['main']['file'])
                    + (int) isset($data['images']['thumbnail']['file'])
                    + count((array) ($data['images']['gallery'] ?? [])),
                'errors' => $item['errors'],
            ];

            if ($item['errors']) {
                continue;
            }

            $category = (string) ($data['category']['name'] ?? '');

            if ($category !== '' && ! $knownCategories->has(CardPackage::nameKey($category))) {
                $key = CardPackage::nameKey($category);
                $categories[$key] ??= ['key' => $key, 'name' => $category, 'species' => 0];
                $categories[$key]['species']++;
            }

            foreach ((array) ($data['tags'] ?? []) as $tag) {
                $name = (string) ($tag['name'] ?? '');
                $key = CardPackage::nameKey($name);

                if ($name === '' || $knownTags->has($key) || $knownTags->has('slug:'.($tag['slug'] ?? ''))) {
                    continue;
                }

                $tags[$key] ??= ['key' => $key, 'name' => $name, 'species' => 0];
                $tags[$key]['species']++;
            }

            foreach ((array) ($data['locations'] ?? []) as $location) {
                $zone = (string) ($location['zone'] ?? '');

                if ($zone === '' || $knownZones->has(CardPackage::nameKey($zone))) {
                    continue;
                }

                $key = CardPackage::nameKey($zone);
                $zones[$key] ??= ['key' => $key, 'name' => $zone, 'locations' => 0];
                $zones[$key]['locations']++;
            }
        }

        $counts = collect($rows)->countBy('status');

        return [
            'counts' => [
                'total' => count($rows),
                'new' => $counts->get('new', 0),
                'existing' => $counts->get('existing', 0),
                'invalid' => $counts->get('invalid', 0),
            ],
            'rows' => $rows,
            'missing_categories' => array_values($categories),
            'missing_tags' => array_values($tags),
            'missing_zones' => array_values($zones),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validateSpecies(string $slug, array $data): array
    {
        $validator = Validator::make($data, [
            'slug' => ['required', 'string', Rule::in([$slug])],
            'common_name' => ['required', 'string', 'max:255'],
            'scientific_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'origin' => ['nullable', 'string', 'max:255'],
            'habitats' => ['nullable', 'array'],
            'habitats.*' => [Rule::enum(Habitat::class)],
            'diet' => ['nullable', Rule::enum(Diet::class)],
            'conservation_status' => ['nullable', Rule::enum(ConservationStatus::class)],
            'is_active' => ['nullable', 'boolean'],
            'category' => ['required', 'array'],
            'category.name' => ['required', 'string', 'max:255'],
            'category.description' => ['nullable', 'string'],
            'tags' => ['array'],
            'tags.*.name' => ['required', 'string', 'max:255'],
            'tags.*.slug' => ['nullable', 'string', 'max:255'],
            'images' => ['array'],
            'images.main' => ['nullable', 'array'],
            'images.thumbnail' => ['nullable', 'array'],
            'images.gallery' => ['array'],
            'images.*.file' => ['sometimes', 'string'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.original_bytes' => ['nullable', 'integer', 'min:1'],
            // Punto focal (0 a 1), opcional: los paquetes anteriores no lo traen.
            'images.*.focal_x' => ['nullable', 'numeric', 'between:0,1'],
            'images.*.focal_y' => ['nullable', 'numeric', 'between:0,1'],
            'images.gallery.*.file' => ['required', 'string'],
            'images.gallery.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.gallery.*.original_bytes' => ['nullable', 'integer', 'min:1'],
            'images.gallery.*.focal_x' => ['nullable', 'numeric', 'between:0,1'],
            'images.gallery.*.focal_y' => ['nullable', 'numeric', 'between:0,1'],
            'images.gallery.*.is_active' => ['nullable', 'boolean'],
            // Formato 3: créditos de cada imagen (recurso "image", sin based_on_asset).
            ...self::imageCreditRules('images.main.credits'),
            ...self::imageCreditRules('images.thumbnail.credits'),
            ...self::imageCreditRules('images.gallery.*.credits'),
            'locations' => ['array'],
            'locations.*.name' => ['nullable', 'string', 'max:255'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.description' => ['nullable', 'string'],
            'locations.*.is_active' => ['nullable', 'boolean'],
            'locations.*.zone' => ['nullable', 'string', 'max:255'],
        ]);

        $errors = $validator->errors()->all();

        // Cada imagen nombrada debe estar en el zip, en su carpeta y con el nombre de su tipo.
        $files = [];

        foreach (['main', 'thumbnail'] as $type) {
            if (isset($data['images'][$type]['file'])) {
                $files[] = [$type, (string) $data['images'][$type]['file'], '#^'.$type.'\.(webp|png|jpe?g)$#'];
            }
        }

        foreach ((array) ($data['images']['gallery'] ?? []) as $image) {
            $files[] = ['gallery', (string) ($image['file'] ?? ''), '#^gallery/[0-9]{2,4}\.(webp|png|jpe?g)$#'];
        }

        foreach ($files as [$type, $name, $pattern]) {
            if (! preg_match($pattern, $name)
                || ! isset($this->manifest['files'][CardPackage::speciesDirectory($slug).'/'.$name])) {
                $errors[] = "La imagen {$type} no está en el paquete.";
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Reglas de los créditos de una imagen de especie.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function imageCreditRules(string $prefix): array
    {
        return [
            $prefix => ['array'],
            $prefix.'.*.asset' => ['required', Rule::in([CreditAsset::Image->value])],
            $prefix.'.*.role' => ['required', Rule::enum(CreditRole::class)],
            $prefix.'.*.source_name' => ['nullable', Rule::enum(AssetSource::class)],
            $prefix.'.*.based_on_asset' => ['prohibited'],
            $prefix.'.*.license_slug' => ['nullable', 'string', 'max:255'],
            $prefix.'.*.acquired_at' => ['nullable', 'date'],
            $prefix.'.*.ai_checked_at' => ['nullable', 'date'],
            $prefix.'.*.modified' => ['nullable', 'boolean'],
            $prefix.'.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    /**
     * Créditos de las imágenes de una especie del paquete (formato 3).
     *
     * @param  array<string, mixed>  $data  species.json
     * @return list<array<string, mixed>>
     */
    private static function speciesImageCredits(array $data): array
    {
        $images = (array) ($data['images'] ?? []);
        $credits = [];

        foreach ([$images['main'] ?? null, $images['thumbnail'] ?? null, ...array_values((array) ($images['gallery'] ?? []))] as $image) {
            foreach ((array) ($image['credits'] ?? []) as $credit) {
                $credits[] = (array) $credit;
            }
        }

        return $credits;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function validateCard(string $uuid, array $data): array
    {
        $validator = Validator::make($data, [
            'uuid' => ['required', 'uuid', Rule::in([$uuid])],
            'species' => ['required', 'array'],
            'species.slug' => ['nullable', 'string', 'max:255', 'required_without:species.scientific_name'],
            'species.scientific_name' => ['nullable', 'string', 'max:255'],
            'species.common_name' => ['nullable', 'string', 'max:255'],
            'attributes' => ['required', 'array'],
            'attributes.name' => ['required', 'string', 'max:255'],
            'attributes.rarity' => ['required', Rule::enum(CardRarity::class)],
            'attributes.is_active' => ['nullable', 'boolean'],
            'attributes.duel_settings_source' => ['nullable', Rule::in([Card::DUEL_SETTINGS_RARITY, Card::DUEL_SETTINGS_CARD])],
            'attributes.messages_source' => ['nullable', Rule::in([Card::MESSAGES_DEFAULT, Card::MESSAGES_CARD])],
            'attributes.image_original_bytes' => ['nullable', 'array'],
            'attributes.sort_order' => ['nullable', 'integer'],
            'attributes.astucia' => ['nullable', 'integer'],
            'attributes.rapidez' => ['nullable', 'integer'],
            'attributes.fuerza' => ['nullable', 'integer'],
            'attributes.title_font_weight' => ['nullable', 'integer'],
            'attributes.questions_per_duel' => ['nullable', 'integer', 'min:1'],
            'attributes.retries' => ['nullable', 'integer', 'min:1'],
            'attributes.cooldown_minutes' => ['nullable', 'integer', 'min:0'],
            'attributes.model_target_height' => ['nullable', 'numeric'],
            'attributes.model_original_height' => ['nullable', 'numeric'],
            'attributes.model_final_height' => ['nullable', 'numeric'],
            'attributes.model_final_width' => ['nullable', 'numeric'],
            'attributes.model_final_depth' => ['nullable', 'numeric'],
            'attributes.fade_color' => ['nullable', 'string', 'max:7'],
            'attributes.accent_color' => ['nullable', 'string', 'max:5'],
            'attributes.title_font_family' => ['nullable', 'string', 'max:255'],
            'attributes.title_font_size' => ['nullable', 'string', 'max:4'],
            'attributes.win_message' => ['nullable', 'string', 'max:255'],
            'attributes.retry_message' => ['nullable', 'string', 'max:255'],
            'attributes.locked_message' => ['nullable', 'string', 'max:255'],
            'files' => ['array'],
            'questions' => ['array'],
            'questions.*.question' => ['required', 'string', 'max:1000'],
            'questions.*.is_active' => ['nullable', 'boolean'],
            'questions.*.answers' => ['required', 'array', 'min:1'],
            'questions.*.answers.*.answer' => ['required', 'string', 'max:1000'],
            'questions.*.answers.*.is_correct' => ['required', 'boolean'],
            'questions.*.answers.*.sort_order' => ['nullable', 'integer'],
            'qr_codes' => ['array'],
            'qr_codes.*.label' => ['nullable', 'string', 'max:255'],
            'qr_codes.*.is_active' => ['nullable', 'boolean'],
            'qr_codes.*.expires_at' => ['nullable', 'date'],
            'qr_codes.*.token' => ['nullable', 'string', 'alpha_num', 'size:64'],
            'credits' => ['array'],
            'credits.*.asset' => ['required', Rule::enum(CreditAsset::class)->only(CreditAsset::forCards())],
            'credits.*.role' => ['required', Rule::enum(CreditRole::class)],
            'credits.*.source_name' => ['nullable', Rule::enum(AssetSource::class)],
            'credits.*.based_on_asset' => ['nullable', Rule::enum(CreditAsset::class)->only(CreditAsset::forCards())],
            'credits.*.license_slug' => ['nullable', 'string', 'max:255'],
            'credits.*.acquired_at' => ['nullable', 'date'],
            'credits.*.ai_checked_at' => ['nullable', 'date'],
            'credits.*.modified' => ['nullable', 'boolean'],
            'credits.*.sort_order' => ['nullable', 'integer'],
        ]);

        $errors = $validator->errors()->all();

        // Cada archivo nombrado debe estar en el zip y en su carpeta.
        $fields = CardPackage::fileFields();

        foreach ((array) ($data['files'] ?? []) as $field => $name) {
            if ($name === null) {
                continue;
            }

            $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

            if (! isset($fields[$field])
                || $name !== $field.'.'.$extension
                || ! in_array($extension, $fields[$field]['extensions'], true)
                || ! isset($this->manifest['files'][CardPackage::cardDirectory($uuid).'/'.$name])) {
                $errors[] = "El archivo {$field} no está en el paquete.";
            }
        }

        return array_values($errors);
    }
}
