<?php

namespace App\Services\CardTransfer;

use App\Enums\AssetSource;
use App\Enums\CardRarity;
use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\License;
use App\Models\PointRule;
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

        if ((int) ($manifest['version'] ?? 0) !== CardPackage::VERSION) {
            throw new InvalidCardPackageException(
                'El paquete usa la versión '.((int) ($manifest['version'] ?? 0)).' del formato; este servidor solo lee la versión '.CardPackage::VERSION.'.'
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

        foreach ($cards as $uuid => $card) {
            $data = $card['data'];
            $species = (array) ($data['species'] ?? []);
            $match = $card['errors'] ? ['species_id' => null, 'match' => null] : $resolver->resolve($species);

            $status = match (true) {
                (bool) $card['errors'] => 'invalid',
                isset($existing[$uuid]) => 'existing',
                default => 'new',
            };

            if (! $card['errors'] && ! $match['species_id']) {
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
                'include_qr_tokens' => $this->includesQrTokens(),
                'total_bytes' => (int) ($this->manifest['total_bytes'] ?? 0),
            ],
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
            'credits.*.asset' => ['required', Rule::enum(CreditAsset::class)],
            'credits.*.role' => ['required', Rule::enum(CreditRole::class)],
            'credits.*.source_name' => ['nullable', Rule::enum(AssetSource::class)],
            'credits.*.based_on_asset' => ['nullable', Rule::enum(CreditAsset::class)],
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
