<?php

namespace Database\Seeders;

use App\Models\License;
use App\Services\Licensing\CreditsCatalog;
use Illuminate\Database\Seeder;

/**
 * Catálogo de licencias. Es idempotente (upsert por slug): se puede volver a
 * ejecutar para actualizar textos o URLs sin tocar los créditos guardados.
 */
class LicenseSeeder extends Seeder
{
    private const CC_URL = 'https://creativecommons.org/licenses/%s/%s/';

    /**
     * Licencias que ya no forman parte del catálogo. Se eliminan si ningún
     * crédito las usa (si alguno las usa, se conservan para no romperlo).
     */
    private const RETIRED_SLUGS = ['tripo-free', 'tripo-paid'];

    private const CC_VERSIONS = ['2.0', '2.5', '3.0', '4.0'];

    /**
     * CC BY y CC BY-SA también existen en 1.0 (hay archivos así en Commons).
     */
    private const CC_OPEN_VERSIONS = ['1.0', ...self::CC_VERSIONS];

    /**
     * Variantes de Creative Commons no seleccionables (uso no comercial o sin
     * obras derivadas): el juego es comercial y las tarjetas modifican la imagen.
     */
    private const CC_RESTRICTED = [
        'by-nc' => [
            'name' => 'Atribución-NoComercial',
            'commercial' => false,
            'modification' => true,
            'share_alike' => false,
            'summary' => 'No seleccionable: prohíbe el uso comercial y el juego es comercial.',
        ],
        'by-nd' => [
            'name' => 'Atribución-SinDerivadas',
            'commercial' => true,
            'modification' => false,
            'share_alike' => false,
            'summary' => 'No seleccionable: prohíbe modificar la obra y la tarjeta recorta y adapta la imagen.',
        ],
        'by-nc-sa' => [
            'name' => 'Atribución-NoComercial-CompartirIgual',
            'commercial' => false,
            'modification' => true,
            'share_alike' => true,
            'summary' => 'No seleccionable: prohíbe el uso comercial y el juego es comercial.',
        ],
        'by-nc-nd' => [
            'name' => 'Atribución-NoComercial-SinDerivadas',
            'commercial' => false,
            'modification' => false,
            'share_alike' => false,
            'summary' => 'No seleccionable: prohíbe el uso comercial y modificar la obra.',
        ],
    ];

    public function run(): void
    {
        $licenses = $this->licenses();

        foreach ($licenses as $index => &$license) {
            $license['sort_order'] = ($index + 1) * 10;
            $license += [
                'url' => null,
                'requires_attribution' => false,
                'allows_commercial' => true,
                'allows_modification' => true,
                'share_alike' => false,
                'attribution_template' => null,
                'is_selectable' => true,
                'is_proprietary' => false,
            ];
        }

        unset($license);

        foreach ($licenses as $license) {
            License::updateOrCreate(
                ['slug' => $license['slug']],
                $license
            );
        }

        License::query()
            ->whereIn('slug', self::RETIRED_SLUGS)
            ->whereDoesntHave('credits')
            ->delete();

        /*
         * DatabaseSeeder desactiva los eventos de los modelos: se invalida
         * aquí la caché de la API de créditos.
         */
        CreditsCatalog::forget();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function licenses(): array
    {
        return [
            [
                'slug' => 'proprietary',
                'name' => 'Obra propia',
                'short_name' => 'Propia',
                'is_proprietary' => true,
                'summary' => 'Creada por el equipo del zoológico: no requiere atribución; en los créditos aparece como obra propia.',
            ],
            [
                'slug' => 'proprietary-commissioned',
                'name' => 'Obra por encargo',
                'short_name' => 'Por encargo',
                'is_proprietary' => true,
                'summary' => 'Encargada con cesión de derechos al zoológico (revisa el contrato): en los créditos aparece como obra por encargo.',
            ],
            [
                'slug' => 'cc0-1.0',
                'name' => 'Creative Commons Cero 1.0 (dominio público)',
                'short_name' => 'CC0 1.0',
                'url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
                'summary' => 'Sin obligaciones: el crédito es opcional, pero se agradece.',
            ],
            [
                'slug' => 'public-domain',
                'name' => 'Dominio público',
                'short_name' => 'Dominio público',
                'url' => 'https://creativecommons.org/publicdomain/mark/1.0/',
                'summary' => 'Sin derechos de autor vigentes: el crédito es opcional. Verifica que lo sea también en México.',
            ],
            ...$this->creativeCommons('by', 'Atribución', 'Uso comercial y cambios permitidos: indica autor, fuente y licencia, y señala los cambios hechos a la obra.', self::CC_OPEN_VERSIONS),
            ...$this->creativeCommons('by-sa', 'Atribución-CompartirIgual', 'Uso comercial y cambios permitidos: indica autor, fuente, licencia y cambios; la tarjeta resultante debe compartirse con la misma licencia.', self::CC_OPEN_VERSIONS, shareAlike: true),
            ...$this->restrictedCreativeCommons(),
            [
                'slug' => 'gfdl',
                'name' => 'Licencia de Documentación Libre de GNU (GFDL)',
                'short_name' => 'GFDL',
                'url' => 'https://www.gnu.org/licenses/fdl-1.3.html',
                'requires_attribution' => true,
                'share_alike' => true,
                'is_selectable' => false,
                'summary' => 'No seleccionable (exige incluir el texto completo de la licencia): si el archivo tiene doble licencia, elige su opción Creative Commons.',
            ],
            [
                'slug' => 'pexels-license',
                'name' => 'Licencia de Pexels',
                'short_name' => 'Licencia de Pexels',
                'url' => 'https://www.pexels.com/license/',
                'summary' => 'Atribución opcional. No vendas la imagen sin modificarla, no la redistribuyas en otro banco de imágenes, no muestres a personas reconocibles de forma ofensiva ni sugieras que una persona o marca respalda el juego.',
            ],
            [
                'slug' => 'unsplash-license',
                'name' => 'Licencia de Unsplash',
                'short_name' => 'Licencia de Unsplash',
                'url' => 'https://unsplash.com/license',
                'summary' => 'Atribución opcional. No vendas la imagen sin modificarla de forma significativa ni la reúnas para crear un servicio similar. La licencia no cubre a las personas ni las marcas que aparezcan.',
            ],
            [
                'slug' => 'pixabay-license',
                'name' => 'Licencia de contenido de Pixabay',
                'short_name' => 'Licencia de Pixabay',
                'url' => 'https://pixabay.com/service/license-summary/',
                'summary' => 'Atribución opcional. No vendas ni distribuyas el contenido tal cual, no lo uses como marca y evita imágenes con marcas o logotipos reconocibles: no se pueden usar con fines comerciales.',
            ],
            [
                'slug' => 'meshy-free',
                'name' => 'Meshy (plan gratuito) – Creative Commons Atribución 4.0',
                'short_name' => 'CC BY 4.0',
                'url' => 'https://creativecommons.org/licenses/by/4.0/',
                'requires_attribution' => true,
                // {basis}: "a partir de la ilustración de la tarjeta", si aplica.
                'attribution_template' => 'Modelo 3D creado con Meshy{basis} – CC BY 4.0',
                'summary' => 'Generado con el plan gratuito: licencia CC BY 4.0, uso comercial permitido citando a Meshy. Elige el plan con el que se generó el modelo.',
            ],
            [
                'slug' => 'meshy-paid',
                'name' => 'Meshy (plan de pago) – licencia privada',
                'short_name' => 'Meshy (pago)',
                'is_proprietary' => true,
                'attribution_template' => 'Modelo 3D creado con Meshy{basis}',
                'summary' => 'Generado con un plan de pago: el modelo es nuestro y no requiere atribución, siempre que no se haya publicado en la comunidad de Meshy.',
            ],
            ...$this->aiTools(),
        ];
    }

    /**
     * Imágenes generadas con IA: los términos de cada herramienta. Una imagen
     * hecha solo con IA probablemente no tiene derechos de autor (no hay
     * autor humano), así que nadie la posee en exclusiva.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aiTools(): array
    {
        $copyright = ' Una imagen hecha solo con IA probablemente no tiene derechos de autor: otros podrían copiarla.';

        $tools = [
            'ai-adobe-firefly' => [
                'Adobe Firefly',
                'Uso comercial permitido con los modelos propios de Firefly (no con modelos de terceros dentro de la app ni con funciones beta). El plan gratuito agrega marca de agua.',
            ],
            'ai-openai' => [
                'ChatGPT / DALL·E',
                'Los términos de OpenAI ceden la imagen al usuario y permiten uso comercial. No la presentes como hecha por una persona.',
            ],
            'ai-google' => [
                'Google Gemini',
                'Usa un plan de pago (Google AI Pro, Workspace o la API de pago): el gratuito no deja claro el uso comercial. Las imágenes llevan la marca invisible SynthID.',
            ],
            'ai-midjourney-paid' => [
                'Midjourney',
                'Requiere un plan de pago; una empresa con más de US$1 M de ingresos al año necesita el plan Pro o Mega. Las imágenes son públicas salvo en modo Stealth.',
            ],
            'ai-stable-diffusion' => [
                'Stable Diffusion',
                'SD 1.5 y SDXL (CreativeML OpenRAIL) permiten uso comercial; SD 3 y 3.5 solo si la organización ingresa menos de US$1 M al año (Stability Community License).',
            ],
        ];

        return array_map(fn (string $slug) => [
            'slug' => $slug,
            'name' => "Términos de {$tools[$slug][0]} (imagen generada con IA)",
            'short_name' => "IA · {$tools[$slug][0]}",
            // {basis}: "a partir de la ilustración de la tarjeta", si aplica.
            'attribution_template' => "Imagen generada con IA ({$tools[$slug][0]}){basis}",
            'summary' => $tools[$slug][1].$copyright,
        ], array_keys($tools));
    }

    /**
     * Versiones de una licencia Creative Commons (por defecto 2.0 a 4.0).
     *
     * @param  array<int, string>  $versions
     * @return array<int, array<string, mixed>>
     */
    private function creativeCommons(
        string $code,
        string $name,
        string $summary,
        array $versions = self::CC_VERSIONS,
        bool $shareAlike = false
    ): array {
        return array_map(fn (string $version) => [
            'slug' => "cc-{$code}-{$version}",
            'name' => "Creative Commons {$name} {$version}",
            'short_name' => 'CC '.strtoupper($code)." {$version}",
            'url' => sprintf(self::CC_URL, $code, $version),
            'requires_attribution' => true,
            'share_alike' => $shareAlike,
            'summary' => $summary,
        ], $versions);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function restrictedCreativeCommons(): array
    {
        $licenses = [];

        foreach (self::CC_RESTRICTED as $code => $variant) {
            foreach ($this->creativeCommons($code, $variant['name'], $variant['summary'], shareAlike: $variant['share_alike']) as $license) {
                $licenses[] = [
                    ...$license,
                    'allows_commercial' => $variant['commercial'],
                    'allows_modification' => $variant['modification'],
                    'is_selectable' => false,
                ];
            }
        }

        return $licenses;
    }
}
