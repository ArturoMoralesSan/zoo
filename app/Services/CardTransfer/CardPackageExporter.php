<?php

namespace App\Services\CardTransfer;

use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\CardRaritySetting;
use App\Models\Credit;
use App\Models\DuelMessageSetting;
use App\Models\License;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Arma el paquete .zip con todas las tarjetas, su configuración y sus archivos.
 */
class CardPackageExporter
{
    /**
     * Archivos temporales que se borran al cerrar el zip (discos no locales).
     *
     * @var list<string>
     */
    private array $temporary = [];

    /**
     * @return array{cards: int, questions: int, qr_codes: int, credits: int, files: int, bytes: int, missing_files: list<array{card: string, field: string}>}
     */
    public function export(string $absolutePath, bool $includeQrTokens): array
    {
        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        $zip = new ZipArchive;

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo del paquete.');
        }

        $files = [];
        $counts = ['cards' => 0, 'questions' => 0, 'qr_codes' => 0, 'credits' => 0];
        $missing = [];
        $licenseIds = [];

        $addString = function (string $entry, string $contents) use ($zip, &$files) {
            $zip->addFromString($entry, $contents);
            $files[$entry] = ['sha256' => hash('sha256', $contents), 'bytes' => strlen($contents)];
        };

        try {
            Card::query()
                ->with([
                    'species:id,slug,scientific_name,common_name',
                    'quizQuestions.answers',
                    'qrCodes',
                    'credits.license:id,slug',
                ])
                ->orderBy('id')
                ->chunkById(50, function ($cards) use (
                    $zip,
                    $includeQrTokens,
                    $addString,
                    &$files,
                    &$counts,
                    &$missing,
                    &$licenseIds
                ) {
                    foreach ($cards as $card) {
                        /** @var Card $card */
                        $entries = $this->addCardFiles($zip, $card, $files, $missing);

                        $data = $this->cardData($card, $includeQrTokens, $entries);

                        $addString(CardPackage::cardJson($card->uuid), CardPackage::encode($data));

                        $counts['cards']++;
                        $counts['questions'] += count($data['questions']);
                        $counts['qr_codes'] += count($data['qr_codes']);
                        $counts['credits'] += count($data['credits']);

                        foreach ($card->credits as $credit) {
                            if ($credit->license_id) {
                                $licenseIds[$credit->license_id] = true;
                            }
                        }
                    }
                });

            $addString(CardPackage::RARITY_SETTINGS, CardPackage::encode($this->raritySettings()));
            $addString(CardPackage::DUEL_MESSAGES, CardPackage::encode(
                DuelMessageSetting::current()->only(['win_message', 'retry_message', 'locked_message'])
            ));
            $addString(CardPackage::LICENSES, CardPackage::encode(
                License::query()
                    ->whereIn('id', array_keys($licenseIds))
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (License $license) => $license->only(CardPackage::LICENSE_FIELDS))
                    ->values()
                    ->all()
            ));

            $bytes = array_sum(array_column($files, 'bytes'));

            $zip->addFromString(CardPackage::MANIFEST, CardPackage::encode([
                'format' => CardPackage::FORMAT,
                'version' => CardPackage::VERSION,
                'app' => config('app.name'),
                'source_url' => config('app.url'),
                'exported_at' => now()->toIso8601String(),
                'include_qr_tokens' => $includeQrTokens,
                'counts' => $counts,
                'total_bytes' => $bytes,
                'files' => $files,
            ]));

            if (! $zip->close()) {
                throw new RuntimeException('No se pudo terminar de escribir el paquete.');
            }
        } catch (\Throwable $exception) {
            @$zip->close();
            @unlink($absolutePath);

            throw $exception;
        } finally {
            $this->cleanTemporary();
        }

        return [
            ...$counts,
            'files' => count($files),
            'bytes' => $bytes,
            'missing_files' => $missing,
        ];
    }

    /**
     * Agrega los archivos de la tarjeta al zip.
     *
     * @param  array<string, array{sha256: string, bytes: int}>  $files
     * @param  list<array{card: string, field: string}>  $missing
     * @return array<string, string|null> Campo → nombre del archivo dentro de la carpeta de la tarjeta.
     */
    private function addCardFiles(ZipArchive $zip, Card $card, array &$files, array &$missing): array
    {
        $entries = [];

        foreach (CardPackage::fileFields() as $field => $spec) {
            $path = $card->{$field};
            $entries[$field] = null;

            if (! $path) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $source = in_array($extension, $spec['extensions'], true)
                ? $this->localPath($spec['disk'], $path)
                : null;

            if (! $source) {
                $missing[] = ['card' => $card->name, 'field' => $field];

                continue;
            }

            $hash = hash_file('sha256', $source);

            if ($hash === false) {
                $missing[] = ['card' => $card->name, 'field' => $field];

                continue;
            }

            $name = $field.'.'.$extension;
            $entry = CardPackage::cardDirectory($card->uuid).'/'.$name;

            $zip->addFile($source, $entry);
            // Imágenes WebP y GLB ya vienen comprimidos: guardarlos sin comprimir es más rápido.
            $zip->setCompressionName($entry, ZipArchive::CM_STORE);

            $files[$entry] = ['sha256' => $hash, 'bytes' => (int) filesize($source)];
            $entries[$field] = $name;
        }

        return $entries;
    }

    /**
     * Ruta local del archivo; en discos remotos se copia a un temporal.
     */
    private function localPath(string $disk, string $path): ?string
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return null;
        }

        try {
            $local = $storage->path($path);

            if (is_file($local)) {
                return $local;
            }
        } catch (\Throwable) {
            // Disco sin rutas locales: se copia abajo.
        }

        $temporary = tempnam(sys_get_temp_dir(), 'card-export-');
        $stream = $storage->readStream($path);

        if (! $temporary || ! $stream) {
            return null;
        }

        file_put_contents($temporary, $stream);
        fclose($stream);
        $this->temporary[] = $temporary;

        return $temporary;
    }

    private function cleanTemporary(): void
    {
        foreach ($this->temporary as $file) {
            @unlink($file);
        }

        $this->temporary = [];
    }

    /**
     * @param  array<string, string|null>  $files
     * @return array<string, mixed>
     */
    private function cardData(Card $card, bool $includeQrTokens, array $files): array
    {
        $attributes = [];

        foreach (CardPackage::CARD_FIELDS as $field) {
            $value = $card->getAttribute($field);
            $attributes[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return [
            'uuid' => $card->uuid,
            'species' => [
                'slug' => $card->species?->slug,
                'scientific_name' => $card->species?->scientific_name,
                'common_name' => $card->species?->common_name,
            ],
            'attributes' => $attributes,
            'files' => $files,
            'questions' => $card->quizQuestions
                ->map(fn (QuizQuestion $question) => [
                    'question' => $question->question,
                    'is_active' => (bool) $question->is_active,
                    'answers' => $question->answers
                        ->map(fn (QuizAnswer $answer) => [
                            'answer' => $answer->answer,
                            'is_correct' => (bool) $answer->is_correct,
                            'sort_order' => (int) $answer->sort_order,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'qr_codes' => $card->qrCodes
                ->sortBy('id')
                ->map(fn (CardQrCode $qr) => [
                    'label' => $qr->label,
                    'is_active' => (bool) $qr->is_active,
                    'expires_at' => $qr->expires_at?->toIso8601String(),
                    'token' => $includeQrTokens ? $qr->token : null,
                ])
                ->values()
                ->all(),
            'credits' => $card->credits
                ->sortBy(fn (Credit $credit) => $credit->sortKey())
                ->map(fn (Credit $credit) => [
                    ...collect(CardPackage::CREDIT_FIELDS)
                        ->mapWithKeys(fn (string $field) => [$field => $this->creditValue($credit, $field)])
                        ->all(),
                    'license_slug' => $credit->license?->slug,
                ])
                ->values()
                ->all(),
        ];
    }

    private function creditValue(Credit $credit, string $field): mixed
    {
        $value = $credit->getAttribute($field);

        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $field === 'acquired_at'
                ? $value->format('Y-m-d')
                : $value->format(DATE_ATOM),
            default => $value,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function raritySettings(): array
    {
        return CardRaritySetting::query()
            ->with('pointRule:id,type')
            ->orderBy('id')
            ->get()
            ->map(fn (CardRaritySetting $setting) => [
                'rarity' => $setting->rarity->value,
                'questions_per_duel' => $setting->questions_per_duel,
                'retries' => $setting->retries,
                'cooldown_minutes' => $setting->cooldown_minutes,
                'reveal_answers_on_lockout' => (bool) $setting->reveal_answers_on_lockout,
                'point_rule_type' => $setting->pointRule?->type,
            ])
            ->values()
            ->all();
    }
}
