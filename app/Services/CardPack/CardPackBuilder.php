<?php

namespace App\Services\CardPack;

use App\Enums\CardPackKind;
use App\Models\Card;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copias cifradas de los archivos de las tarjetas para el paquete sin conexión.
 * Ver docs/offline-packs-backend.md.
 *
 * Cada archivo se cifra con AES-256-GCM y una llave propia derivada de su ruta.
 * Las rutas nunca cambian de contenido (nombres uniqid), así que la llave no se
 * guarda: se vuelve a derivar cuando la API tiene que enviarla.
 */
class CardPackBuilder
{
    private const CIPHER = 'aes-256-gcm';

    public const IV_BYTES = 12;

    private const TAG_BYTES = 16;

    public function enabled(): bool
    {
        return (bool) config('cards.packs.enabled');
    }

    /**
     * Versión del archivo: su nombre sin extensión. Cambia con cada archivo nuevo.
     */
    public static function fileId(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Llave AES de 32 bytes del archivo en esa ruta.
     */
    public function key(string $path): string
    {
        return hash_hmac('sha256', 'card-pack:v1:'.$path, $this->secret(), true);
    }

    public function binPath(int $cardId, string $fileId): string
    {
        return $this->directory($cardId).'/'.$fileId.'.bin';
    }

    public function directory(int $cardId): string
    {
        return trim((string) config('cards.packs.directory'), '/').'/'.$cardId;
    }

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('cards.packs.disk'));
    }

    /**
     * Ruta del archivo original, o null si la tarjeta no lo tiene o es una URL
     * externa (esas no se empaquetan).
     */
    public static function sourcePath(Card $card, CardPackKind $kind): ?string
    {
        $path = $card->{$kind->column()};

        if (! is_string($path) || $path === '' || preg_match('#^https?://#', $path)) {
            return null;
        }

        return $path;
    }

    /**
     * Referencias `{ id, key }` que la API envía junto a las URL en claro.
     *
     * @param  list<CardPackKind>  $kinds
     * @return array<string, array{id: string, key: string}|null>
     */
    public function refs(Card $card, array $kinds): array
    {
        $refs = [];

        foreach ($kinds as $kind) {
            $path = self::sourcePath($card, $kind);

            $refs[$kind->value] = $path === null ? null : [
                'id' => self::fileId($path),
                'key' => base64_encode($this->key($path)),
            ];
        }

        return $refs;
    }

    /**
     * Deja la carpeta de la tarjeta con un .bin por cada archivo actual:
     * cifra los que falten y borra los demás. Idempotente.
     *
     * @return bool Si escribió o borró algo.
     */
    public function sync(Card $card, bool $force = false): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $disk = $this->disk();
        $expected = [];
        $changed = false;

        foreach (CardPackKind::cases() as $kind) {
            $path = self::sourcePath($card, $kind);

            // Sin original no hay copia: la app usa la URL o nada.
            if ($path === null || ! Storage::disk($kind->disk())->exists($path)) {
                continue;
            }

            $binPath = $this->binPath($card->id, self::fileId($path));
            $expected[] = $binPath;

            if (! $force && $disk->exists($binPath)) {
                continue;
            }

            $this->write($binPath, $this->encrypt(
                (string) Storage::disk($kind->disk())->get($path),
                $this->key($path)
            ));
            $changed = true;
        }

        foreach ($disk->files($this->directory($card->id)) as $file) {
            if (! in_array($file, $expected, true)) {
                $disk->delete($file);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Borra todas las copias de una tarjeta eliminada.
     */
    public function remove(int $cardId): void
    {
        if ($this->enabled()) {
            $this->disk()->deleteDirectory($this->directory($cardId));
        }
    }

    /**
     * `iv (12) | texto cifrado | etiqueta (16)`, el formato "combined" que
     * espera `AESSealedData.fromCombined` de expo-crypto.
     */
    public function encrypt(string $plain, string $key): string
    {
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';

        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);

        if ($cipher === false) {
            throw new RuntimeException('No se pudo cifrar el archivo del paquete.');
        }

        return $iv.$cipher.$tag;
    }

    /**
     * Inverso de encrypt(); null si la llave no corresponde o el archivo está dañado.
     */
    public function decrypt(string $sealed, string $key): ?string
    {
        if (strlen($sealed) < self::IV_BYTES + self::TAG_BYTES) {
            return null;
        }

        $plain = openssl_decrypt(
            substr($sealed, self::IV_BYTES, -self::TAG_BYTES),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            substr($sealed, 0, self::IV_BYTES),
            substr($sealed, -self::TAG_BYTES)
        );

        return $plain === false ? null : $plain;
    }

    /**
     * Se escribe aparte y luego se mueve, para que nunca se publique (ni se
     * calcule el hash de) un archivo a medias.
     */
    private function write(string $binPath, string $contents): void
    {
        $disk = $this->disk();
        $temporary = $binPath.'.tmp';

        $disk->put($temporary, $contents);
        $disk->delete($binPath);
        $disk->move($temporary, $binPath);
    }

    private function secret(): string
    {
        $secret = config('cards.packs.secret');

        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        return hash_hmac('sha256', 'card-pack', (string) config('app.key'), true);
    }
}
