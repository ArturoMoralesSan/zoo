<?php

namespace App\Services\CardTransfer;

use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\CardRaritySetting;
use App\Models\DuelMessageSetting;
use App\Models\License;
use App\Models\PointRule;
use App\Models\QuizQuestion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Importa un paquete ya validado por CardPackageReader.
 *
 * Cada tarjeta se guarda en su propia transacción: si una falla, sus archivos
 * nuevos se borran y las demás siguen. Los archivos se copian tal cual (ya
 * vienen optimizados y escalados).
 */
class CardPackageImporter
{
    public const MODE_SKIP = 'skip';

    public const MODE_OVERWRITE = 'overwrite';

    /** @var array<string, int> */
    private array $licenseIds = [];

    /** @var list<string> */
    private array $createdLicenses = [];

    /**
     * @param  array<string, int|null>  $speciesMap  Clave de especie del paquete → id elegido (null = omitir).
     * @param  (callable(int, int): void)|null  $progress
     * @return array<string, mixed>
     */
    public function import(
        CardPackageReader $reader,
        string $mode,
        bool $replaceSettings,
        array $speciesMap = [],
        ?callable $progress = null
    ): array {
        $resolver = new SpeciesResolver;
        $packageLicenses = $reader->licenses();
        $useTokens = $reader->includesQrTokens();

        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => [],
            'failed' => [],
            'settings_replaced' => false,
            'qr_tokens_regenerated' => 0,
            'licenses_created' => [],
        ];

        if ($replaceSettings) {
            $this->importSettings($reader);
            $result['settings_replaced'] = true;
        }

        $cards = $reader->cards();
        $total = count($cards);
        $done = 0;

        foreach ($cards as $uuid => $card) {
            $data = $card['data'];
            $name = (string) ($data['attributes']['name'] ?? $uuid);

            $progress && $progress(++$done, $total);

            if ($card['errors']) {
                $result['skipped'][] = ['name' => $name, 'reason' => 'Datos inválidos: '.$card['errors'][0]];

                continue;
            }

            $speciesId = $this->speciesId($resolver, (array) $data['species'], $speciesMap);

            if (! $speciesId) {
                $result['skipped'][] = ['name' => $name, 'reason' => 'Su especie no existe en este servidor.'];

                continue;
            }

            $existing = Card::query()->where('uuid', $uuid)->first();

            if ($existing && $mode !== self::MODE_OVERWRITE) {
                $result['skipped'][] = ['name' => $name, 'reason' => 'Ya existe.'];

                continue;
            }

            try {
                $regenerated = $this->importCard($reader, $uuid, $data, $speciesId, $existing, $useTokens, $packageLicenses);

                $result[$existing ? 'updated' : 'created']++;
                $result['qr_tokens_regenerated'] += $regenerated;
            } catch (Throwable $exception) {
                report($exception);

                $result['failed'][] = [
                    'name' => $name,
                    'message' => $exception instanceof InvalidCardPackageException
                        ? $exception->getMessage()
                        : 'No se pudo guardar la tarjeta.',
                ];
            }
        }

        $result['licenses_created'] = $this->createdLicenses;

        return $result;
    }

    /**
     * @param  array{slug?: ?string, scientific_name?: ?string}  $species
     * @param  array<string, int|null>  $speciesMap
     */
    private function speciesId(SpeciesResolver $resolver, array $species, array $speciesMap): ?int
    {
        $key = SpeciesResolver::key($species);

        // Lo que eligió la persona en la vista previa manda.
        if (array_key_exists($key, $speciesMap)) {
            return $speciesMap[$key] ?: null;
        }

        return $resolver->resolve($species)['species_id'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $packageLicenses
     * @return int QR cuyo token se regeneró por estar en uso en otra tarjeta.
     */
    private function importCard(
        CardPackageReader $reader,
        string $uuid,
        array $data,
        int $speciesId,
        ?Card $existing,
        bool $useTokens,
        array $packageLicenses
    ): int {
        // [disco, ruta] escritos (se borran si falla) y reemplazados (se borran si todo sale bien).
        $written = [];
        $obsolete = [];
        $regenerated = 0;

        try {
            DB::transaction(function () use (
                $reader, $uuid, $data, $speciesId, $existing, $useTokens, $packageLicenses,
                &$written, &$obsolete, &$regenerated
            ) {
                $card = $existing ?? new Card(['uuid' => $uuid]);

                $card->fill([
                    ...$this->cardAttributes((array) $data['attributes']),
                    'species_id' => $speciesId,
                ]);
                $card->save();

                foreach (CardPackage::fileFields() as $field => $spec) {
                    $name = $data['files'][$field] ?? null;
                    $old = $card->{$field};

                    if ($name) {
                        $directory = trim('cards/'.$card->id.'/'.$spec['directory'], '/');
                        $path = $directory.'/'.uniqid('', true).'.'.pathinfo($name, PATHINFO_EXTENSION);
                        $stream = $reader->stream(CardPackage::cardDirectory($uuid).'/'.$name);

                        try {
                            if (! Storage::disk($spec['disk'])->writeStream($path, $stream)) {
                                throw new RuntimeException("No se pudo guardar {$field}.");
                            }
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        $written[] = [$spec['disk'], $path];
                        $card->{$field} = $path;
                    } else {
                        $card->{$field} = null;
                    }

                    if ($old && $old !== $card->{$field}) {
                        $obsolete[] = [$spec['disk'], $old];
                    }
                }

                $card->save();

                $this->syncQuestions($card, (array) ($data['questions'] ?? []));
                $regenerated = $this->syncQrCodes($card, (array) ($data['qr_codes'] ?? []), $useTokens, $existing !== null);
                $this->replaceCredits($card, (array) ($data['credits'] ?? []), $packageLicenses);
            });
        } catch (Throwable $exception) {
            $this->deleteFiles($written);

            throw $exception;
        }

        $this->deleteFiles($obsolete);

        return $regenerated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function cardAttributes(array $attributes): array
    {
        $values = array_intersect_key($attributes, array_flip(CardPackage::CARD_FIELDS));

        // Sin especificar, como al crear la tarjeta en el panel.
        $values['is_active'] = (bool) ($values['is_active'] ?? false);
        $values['sort_order'] = (int) ($values['sort_order'] ?? 0);

        return $values;
    }

    /**
     * Las preguntas se reconocen por su texto normalizado. Nunca se borran:
     * los intentos en curso guardan sus id (quiz_attempts.question_ids), así
     * que las que ya no vienen en el paquete solo se desactivan.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function syncQuestions(Card $card, array $questions): void
    {
        $current = $card->quizQuestions()->with('answers')->get()
            ->keyBy(fn (QuizQuestion $question) => QuizQuestion::normalizeText($question->question));

        $kept = [];

        foreach ($questions as $item) {
            $key = QuizQuestion::normalizeText((string) $item['question']);

            if (isset($kept[$key])) {
                continue;
            }

            $answers = collect($item['answers'] ?? [])
                ->values()
                ->map(fn (array $answer, int $index) => [
                    'answer' => (string) $answer['answer'],
                    'is_correct' => (bool) $answer['is_correct'],
                    'sort_order' => (int) ($answer['sort_order'] ?? $index),
                ]);

            /** @var QuizQuestion|null $question */
            $question = $current->get($key);

            if ($question) {
                $question->update([
                    'question' => (string) $item['question'],
                    'is_active' => (bool) ($item['is_active'] ?? true),
                ]);

                $same = $question->answers
                    ->map(fn ($answer) => [
                        'answer' => $answer->answer,
                        'is_correct' => (bool) $answer->is_correct,
                        'sort_order' => (int) $answer->sort_order,
                    ])
                    ->values()
                    ->all() === $answers->all();

                // Solo se reemplazan si cambiaron: un duelo en curso usa sus id.
                if (! $same) {
                    $question->answers()->delete();
                    $question->answers()->createMany($answers->all());
                }
            } else {
                $question = $card->quizQuestions()->create([
                    'question' => (string) $item['question'],
                    'is_active' => (bool) ($item['is_active'] ?? true),
                ]);
                $question->answers()->createMany($answers->all());
            }

            $kept[$key] = true;
        }

        $current
            ->reject(fn (QuizQuestion $question, string $key) => isset($kept[$key]))
            ->each(fn (QuizQuestion $question) => $question->update(['is_active' => false]));
    }

    /**
     * Con tokens: el QR impreso sigue sirviendo. Si el token ya lo usa otra
     * tarjeta, se crea con uno nuevo. Sin tokens: solo las tarjetas nuevas
     * reciben QR (con tokens nuevos); las existentes conservan los suyos.
     *
     * @param  array<int, array<string, mixed>>  $qrCodes
     */
    private function syncQrCodes(Card $card, array $qrCodes, bool $useTokens, bool $existed): int
    {
        if (! $useTokens && $existed) {
            return 0;
        }

        $regenerated = 0;

        foreach ($qrCodes as $item) {
            $attributes = [
                'label' => $item['label'] ?? null,
                'is_active' => (bool) ($item['is_active'] ?? true),
                'expires_at' => isset($item['expires_at']) ? Carbon::parse($item['expires_at']) : null,
            ];

            $token = $useTokens ? ($item['token'] ?? null) : null;

            if ($token) {
                $current = CardQrCode::query()->where('token', $token)->first();

                if ($current && $current->card_id === $card->id) {
                    $current->update($attributes);

                    continue;
                }

                if ($current) {
                    $token = null;
                    $regenerated++;
                }
            }

            $qr = new CardQrCode([...$attributes, 'card_id' => $card->id]);

            if ($token) {
                $qr->forceFill(['token' => $token]);
            }

            $qr->save();
        }

        return $regenerated;
    }

    /**
     * Los créditos de la tarjeta se reemplazan por los del paquete. Se borran
     * y crean con Eloquent para que CreditsCacheObserver limpie la caché.
     *
     * @param  array<int, array<string, mixed>>  $credits
     * @param  array<string, array<string, mixed>>  $packageLicenses
     */
    private function replaceCredits(Card $card, array $credits, array $packageLicenses): void
    {
        $card->credits()->get()->each->delete();

        foreach ($credits as $item) {
            $values = array_intersect_key($item, array_flip(CardPackage::CREDIT_FIELDS));
            $values['license_id'] = $this->licenseId($item['license_slug'] ?? null, $packageLicenses);

            $card->credits()->create($values);
        }

        $card->unsetRelation('credits');
    }

    /**
     * Licencia por slug. Si este servidor no la tiene y el paquete trae sus
     * datos, se crea sin poder elegirse hasta que alguien la revise.
     *
     * @param  array<string, array<string, mixed>>  $packageLicenses
     */
    private function licenseId(?string $slug, array $packageLicenses): ?int
    {
        if (! $slug) {
            return null;
        }

        if (isset($this->licenseIds[$slug])) {
            return $this->licenseIds[$slug];
        }

        $id = License::query()->where('slug', $slug)->value('id');

        if (! $id && isset($packageLicenses[$slug])) {
            $id = License::create([
                ...$packageLicenses[$slug],
                'slug' => $slug,
                'is_selectable' => false,
            ])->id;

            $this->createdLicenses[] = $slug;
        }

        return $this->licenseIds[$slug] = $id ?: null;
    }

    private function importSettings(CardPackageReader $reader): void
    {
        $rarity = $reader->raritySettings();
        $messages = $reader->duelMessages();

        DB::transaction(function () use ($rarity, $messages) {
            foreach ($rarity as $row) {
                CardRaritySetting::query()->updateOrCreate(
                    ['rarity' => $row['rarity']],
                    [
                        'questions_per_duel' => $row['questions_per_duel'] ?? null,
                        'retries' => $row['retries'] ?? null,
                        'cooldown_minutes' => $row['cooldown_minutes'] ?? null,
                        'reveal_answers_on_lockout' => (bool) ($row['reveal_answers_on_lockout'] ?? false),
                        // Una regla que no existe aquí deja la rareza sin puntos.
                        'point_rule_id' => isset($row['point_rule_type'])
                            ? PointRule::query()->where('type', $row['point_rule_type'])->value('id')
                            : null,
                    ]
                );
            }

            DuelMessageSetting::current()->update($messages);
        });
    }

    /**
     * @param  array<int, array{0: string, 1: ?string}>  $files
     */
    private function deleteFiles(array $files): void
    {
        foreach ($files as [$disk, $path]) {
            try {
                if ($path && Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
