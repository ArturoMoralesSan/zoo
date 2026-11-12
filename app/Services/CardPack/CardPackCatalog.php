<?php

namespace App\Services\CardPack;

use App\Enums\CardPackKind;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo de archivos cifrados de las tarjetas activas (GET /api/cards/pack).
 * Igual para todos los usuarios y sin llaves. Ver docs/offline-packs-backend.md.
 *
 * Se arma una vez y se guarda en caché; CardPackObserver lo invalida cuando
 * cambia una tarjeta. En caché van rutas relativas: la URL se arma en cada
 * petición con su host (como CardResource::publicUrl()).
 */
class CardPackCatalog
{
    public const CACHE_KEY = 'cards.pack.v1';

    /**
     * Respaldo por si un cambio no pasa por Eloquent (p. ej. SQL manual).
     */
    private const CACHE_TTL = 86400;

    public function __construct(private CardPackBuilder $builder) {}

    /**
     * @return array{generated_at: string, total_bytes: int, files: list<array{id: string, card_id: int, kind: string, url: string|null, bytes: int, sha256: string}>, etag: string}
     */
    public function get(): array
    {
        $catalog = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build());

        $catalog['files'] = array_map(fn (array $file) => [
            'id' => $file['id'],
            'card_id' => $file['card_id'],
            'kind' => $file['kind'],
            'url' => CardResource::publicUrl($file['path']),
            'bytes' => $file['bytes'],
            'sha256' => $file['sha256'],
        ], $catalog['files']);

        return $catalog;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{generated_at: string, total_bytes: int, files: list<array{id: string, card_id: int, kind: string, path: string, bytes: int, sha256: string}>, etag: string}
     */
    private function build(): array
    {
        $disk = $this->builder->disk();
        $files = [];

        $cards = Card::query()
            ->select(['id', 'sort_order', ...array_map(fn (CardPackKind $kind) => $kind->column(), CardPackKind::cases())])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($cards as $card) {
            foreach (CardPackKind::cases() as $kind) {
                $source = CardPackBuilder::sourcePath($card, $kind);

                if ($source === null) {
                    continue;
                }

                $fileId = CardPackBuilder::fileId($source);
                $path = $this->builder->binPath($card->id, $fileId);

                // Solo lo que ya está cifrado; lo demás la app lo pide por URL.
                if (! $disk->exists($path)) {
                    continue;
                }

                $files[] = [
                    'id' => $fileId,
                    'card_id' => $card->id,
                    'kind' => $kind->value,
                    'path' => $path,
                    ...$this->fileMeta($path),
                ];
            }
        }

        return [
            'generated_at' => Carbon::now()->toIso8601String(),
            'total_bytes' => array_sum(array_column($files, 'bytes')),
            'files' => $files,
            'etag' => sha1((string) json_encode($files)),
        ];
    }

    /**
     * Tamaño y hash de un .bin. Solo cambia si se vuelve a cifrar (--force),
     * así que no se recalcula cada vez que se invalida el catálogo. La llave
     * de caché usa el IV del archivo (aleatorio en cada cifrado), no la fecha:
     * dos cifrados en el mismo segundo tendrían la misma fecha.
     *
     * @return array{bytes: int, sha256: string}
     */
    private function fileMeta(string $path): array
    {
        $disk = $this->builder->disk();
        $bytes = $disk->size($path);
        $key = 'cards.pack.file.'.sha1($path.'|'.$bytes.'|'.bin2hex($this->iv($path)));

        return Cache::rememberForever($key, fn () => [
            'bytes' => $bytes,
            'sha256' => hash('sha256', (string) $disk->get($path)),
        ]);
    }

    /**
     * Primeros bytes del .bin (el IV), sin leer el archivo completo.
     */
    private function iv(string $path): string
    {
        $stream = $this->builder->disk()->readStream($path);

        if (! is_resource($stream)) {
            return '';
        }

        $iv = (string) fread($stream, CardPackBuilder::IV_BYTES);
        fclose($stream);

        return $iv;
    }
}
