<?php

namespace App\Models;

use App\Enums\CardTransferStatus;
use App\Enums\CardTransferType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Exportación o importación de un paquete de tarjetas.
 *
 * El .zip vive en el disco `local` (privado) mientras exista la fila.
 *
 * @property int $id
 * @property int|null $user_id
 * @property CardTransferType $type
 * @property CardTransferStatus $status
 * @property array<string, mixed>|null $options
 * @property string|null $file_path
 * @property string|null $original_name
 * @property int|null $file_size
 * @property array<string, mixed>|null $summary
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CardTransfer extends Model
{
    use Prunable;

    public const DISK = 'local';

    public const DIRECTORY = 'card-transfers';

    // Días que se conservan los paquetes antes de borrarlos.
    public const KEEP_DAYS = 7;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'options',
        'file_path',
        'original_name',
        'file_size',
        'summary',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'type' => CardTransferType::class,
            'status' => CardTransferStatus::class,
            'options' => 'array',
            'summary' => 'array',
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function absolutePath(): ?string
    {
        return $this->file_path
            ? Storage::disk(self::DISK)->path($this->file_path)
            : null;
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null
            && Storage::disk(self::DISK)->exists($this->file_path);
    }

    public function deleteFile(): void
    {
        if ($this->hasFile()) {
            Storage::disk(self::DISK)->delete($this->file_path);
        }
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    public function markFailed(string $message, ?array $summary = null): void
    {
        $this->update([
            'status' => CardTransferStatus::Failed,
            'error' => $message,
            'summary' => $summary ?? $this->summary,
        ]);
    }

    /**
     * Paquetes viejos (`php artisan model:prune`, programado a diario).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    // Borrar la fila (también al podarla) borra su paquete.
    protected static function booted(): void
    {
        static::deleting(function (CardTransfer $transfer) {
            $transfer->deleteFile();
        });
    }
}
