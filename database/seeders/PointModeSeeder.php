<?php

namespace Database\Seeders;

use App\Models\PointMode;
use App\Models\PointRule;
use Illuminate\Database\Seeder;

/**
 * Seis temporadas de ejemplo, listas para activarse desde el panel. Todas
 * quedan inactivas: el zoológico decide cuándo empieza cada una. Un modo que
 * ya existe (por nombre) no se toca, para no pisar lo editado en el panel.
 */
class PointModeSeeder extends Seeder
{
    public const MODES = [
        [
            'name' => 'Día de Muertos',
            'description' => 'Para finales de octubre y principios de noviembre. Premia las cartas difíciles y los eventos de la ofrenda; las capturas comunes y raras no cambian.',
            'public_title' => 'Temporada de Día de Muertos',
            'public_message' => 'Recordamos a las especies que ya no están y celebramos a las que seguimos cuidando. Las cartas épicas y de edición especial dan más puntos, y asistir a los eventos de la ofrenda vale el doble.',
            'icon' => 'skull',
            'color' => 'cempasuchil',
            'rules' => [
                'card_capture_epica' => 100,
                'card_capture_edicion_especial' => 150,
                'event_attendance' => 40,
            ],
        ],
        [
            'name' => 'Navidad',
            'description' => 'Para diciembre y vacaciones de invierno. Impulsa visitas familiares y donaciones: más puntos por boleto, por donar y por capturas comunes y raras.',
            'public_title' => '¡Feliz Navidad en el zoológico!',
            'public_message' => 'Regala una visita: cada boleto da el doble de puntos. Las cartas comunes y raras también dan más, y cada donación para el cuidado de los animales suma el doble.',
            'icon' => 'pine-tree',
            'color' => 'rojo-navideno',
            'rules' => [
                'ticket_purchase' => 20,
                'card_capture_comun' => 35,
                'card_capture_rara' => 65,
                'donation' => 10,
            ],
        ],
        [
            'name' => 'Reto de invierno',
            'description' => 'Temporada de dificultad para enero y febrero, cuando hay menos visitantes. Las capturas comunes, raras y épicas dan menos; la edición especial da más para premiar a quien la encuentre.',
            'public_title' => 'Reto de invierno',
            'public_message' => 'Muchos animales descansan con el frío y encontrarlos es más difícil. Las capturas dan menos puntos esta temporada, pero las cartas de edición especial valen 150.',
            'icon' => 'snowflake',
            'color' => 'azul-invierno',
            'rules' => [
                'card_capture_comun' => 15,
                'card_capture_rara' => 35,
                'card_capture_epica' => 60,
                'card_capture_edicion_especial' => 150,
            ],
        ],
        [
            'name' => 'Semana de las tortugas marinas',
            'description' => 'Campaña de conservación alrededor del 16 de junio (Día Mundial de las Tortugas Marinas). Premia donaciones y charlas sobre conservación.',
            'public_title' => 'Semana de las tortugas marinas',
            'public_message' => 'Ayúdanos a proteger a las tortugas marinas: cada donación da el triple de puntos y las charlas de conservación valen el doble. Las cartas raras también dan un poco más.',
            'icon' => 'turtle',
            'color' => 'turquesa',
            'rules' => [
                'donation' => 15,
                'event_attendance' => 40,
                'card_capture_rara' => 60,
            ],
        ],
        [
            'name' => 'Vacaciones de verano',
            'description' => 'Para julio y agosto. Pensado para visitantes nuevos: más puntos por boleto y por las capturas comunes y raras, sin tocar las cartas difíciles.',
            'public_title' => '¡Vacaciones de verano!',
            'public_message' => 'Ven a explorar el zoológico estas vacaciones: los boletos dan más puntos y las cartas comunes y raras también. Es un buen momento para empezar tu colección.',
            'icon' => 'sun',
            'color' => 'dorado',
            'rules' => [
                'ticket_purchase' => 15,
                'card_capture_comun' => 40,
                'card_capture_rara' => 60,
            ],
        ],
        [
            'name' => 'Reto de expertos',
            'description' => 'Evento corto para coleccionistas avanzados. Las cartas comunes no dan puntos (se capturan igual) y las épicas y de edición especial dan mucho más.',
            'public_title' => 'Reto de expertos',
            'public_message' => 'Solo para coleccionistas: durante el reto, las cartas comunes no dan puntos, pero las épicas y las de edición especial dan el doble. ¿Te atreves?',
            'icon' => 'crown',
            'color' => 'morado',
            'rules' => [
                'card_capture_comun' => 0,
                'card_capture_epica' => 150,
                'card_capture_edicion_especial' => 200,
            ],
        ],
    ];

    public function run(): void
    {
        $ruleIds = PointRule::query()->pluck('id', 'type');

        foreach (self::MODES as $example) {
            if (PointMode::query()->where('name', $example['name'])->exists()) {
                continue;
            }

            $mode = PointMode::create([
                'name' => $example['name'],
                'description' => $example['description'],
                'is_active' => false,
                'is_public' => true,
                'public_title' => $example['public_title'],
                'public_message' => $example['public_message'],
                'icon' => $example['icon'],
                'color' => $example['color'],
            ]);

            // Una regla que no existe (borrada en el panel) simplemente se omite.
            foreach ($example['rules'] as $type => $points) {
                if (isset($ruleIds[$type])) {
                    $mode->rules()->attach($ruleIds[$type], ['points' => $points]);
                }
            }
        }
    }
}
