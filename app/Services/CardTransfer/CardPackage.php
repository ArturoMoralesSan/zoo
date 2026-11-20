<?php

namespace App\Services\CardTransfer;

/**
 * Formato del paquete de tarjetas (.zip). Ver docs/card-transfer.md.
 *
 * Nunca lleva id de la base: las tarjetas se reconocen por uuid, las especies
 * por slug, las licencias por slug y las reglas de puntos por tipo.
 */
final class CardPackage
{
    public const FORMAT = 'zoodeck-cards';

    public const VERSION = 1;

    public const MANIFEST = 'manifest.json';

    public const LICENSES = 'licenses.json';

    public const RARITY_SETTINGS = 'settings/rarity.json';

    public const DUEL_MESSAGES = 'settings/duel-messages.json';

    /**
     * Nombres válidos dentro del zip; cualquier otro rechaza el paquete
     * (así nunca se escribe fuera de las carpetas previstas).
     */
    public const ENTRY_PATTERN = '#^(manifest\.json|licenses\.json|settings/(rarity|duel-messages)\.json|cards/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/(card\.json|(thumbnail_image|artwork_image|generated_image)\.(webp|png|jpe?g)|model_(original_)?file\.glb))$#';

    /**
     * Columnas de la tarjeta que viajan en card.json (sin id, especie ni archivos).
     */
    public const CARD_FIELDS = [
        'name',
        'rarity',
        'fade_color',
        'accent_color',
        'title_font_family',
        'title_font_weight',
        'title_font_size',
        'model_target_height',
        'model_original_height',
        'model_final_height',
        'model_final_width',
        'model_final_depth',
        'is_active',
        'sort_order',
        'astucia',
        'rapidez',
        'fuerza',
        'duel_settings_source',
        'questions_per_duel',
        'retries',
        'cooldown_minutes',
        'messages_source',
        'win_message',
        'retry_message',
        'locked_message',
        'image_original_bytes',
    ];

    /**
     * Columnas del crédito que viajan tal cual (la licencia va por slug).
     */
    public const CREDIT_FIELDS = [
        'asset',
        'role',
        'sort_order',
        'title',
        'author_name',
        'author_url',
        'source_name',
        'source_url',
        'acquired_at',
        'modified',
        'modification_note',
        'attribution_override',
        'based_on_asset',
        'ai_prompt',
        'ai_checked_at',
        'notes',
    ];

    public const LICENSE_FIELDS = [
        'slug',
        'name',
        'short_name',
        'url',
        'requires_attribution',
        'allows_commercial',
        'allows_modification',
        'share_alike',
        'attribution_template',
        'is_selectable',
        'is_proprietary',
        'summary',
        'sort_order',
    ];

    /**
     * Archivos de la tarjeta: disco, subcarpeta dentro de cards/{id} y
     * extensiones admitidas.
     *
     * @return array<string, array{disk: string, directory: string, extensions: list<string>}>
     */
    public static function fileFields(): array
    {
        $images = ['webp', 'png', 'jpg', 'jpeg'];

        return [
            'thumbnail_image' => ['disk' => 'public', 'directory' => '', 'extensions' => $images],
            'artwork_image' => ['disk' => 'public', 'directory' => '', 'extensions' => $images],
            'generated_image' => ['disk' => 'public', 'directory' => '', 'extensions' => $images],
            'model_file' => [
                'disk' => (string) config('glb.disks.resized'),
                'directory' => 'models',
                'extensions' => ['glb'],
            ],
            'model_original_file' => [
                'disk' => (string) config('glb.disks.original'),
                'directory' => 'models/originals',
                'extensions' => ['glb'],
            ],
        ];
    }

    public static function cardDirectory(string $uuid): string
    {
        return 'cards/'.$uuid;
    }

    public static function cardJson(string $uuid): string
    {
        return self::cardDirectory($uuid).'/card.json';
    }

    public static function encode(mixed $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
