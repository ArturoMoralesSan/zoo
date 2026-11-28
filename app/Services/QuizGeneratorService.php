<?php

namespace App\Services;

use App\Exceptions\QuizGenerationException;
use App\Models\Card;
use App\Models\Species;
use App\Services\Ai\GeminiClient;

/**
 * Genera preguntas de quiz con Gemini.
 *
 * No guarda nada: devuelve las preguntas en el formato de importación para
 * que una persona las revise, porque la IA puede equivocarse con los datos.
 */
class QuizGeneratorService
{
    public const MIN_QUESTIONS = 1;

    // Tope de la revisión con IA, menor que el de la importación manual para
    // acotar el costo y el tiempo de cada llamada a Gemini.
    public const MAX_QUESTIONS = 50;

    public const TOPICS = [
        'general' => ['label' => 'Todo', 'focus' => null],
        'alimentacion' => ['label' => 'Alimentación', 'focus' => 'su alimentación'],
        'habitat' => ['label' => 'Hábitat', 'focus' => 'su hábitat y distribución'],
        'comportamiento' => ['label' => 'Comportamiento', 'focus' => 'su comportamiento'],
        'conservacion' => ['label' => 'Conservación', 'focus' => 'su conservación y amenazas'],
    ];

    private const MAX_EXCLUDED_QUESTIONS = 100;

    public function __construct(private GeminiClient $gemini) {}

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured();
    }

    /**
     * @param  list<string>  $exclude  Preguntas aún sin guardar que no debe repetir.
     * @return list<array{question: string, correct_answer: string, wrong_answers: list<string>}>
     *
     * @throws QuizGenerationException
     */
    public function generate(Card $card, int $count, string $topic, array $exclude = []): array
    {
        if (! $this->isConfigured()) {
            throw QuizGenerationException::notConfigured();
        }

        $json = $this->gemini->generateJson(
            $this->buildPrompt($card, $count, $topic, $exclude),
            $this->responseSchema(),
            QuizGenerationException::class
        );

        return array_slice($this->parseQuestions($json), 0, $count);
    }

    /**
     * @param  list<string>  $exclude
     */
    private function buildPrompt(Card $card, int $count, string $topic, array $exclude): string
    {
        $card->loadMissing('species');

        /** @var Species|null $species */
        $species = $card->species;

        $subject = $species
            ? trim("{$species->common_name} ({$species->scientific_name})")
            : $card->name;

        $focus = self::TOPICS[$topic]['focus'] ?? null;

        $lines = [
            "Genera {$count} preguntas de opción múltiple sobre {$subject}"
                .($focus ? ", enfocadas en {$focus}," : '')
                ." para la tarjeta \"{$card->name}\" de un juego educativo de un zoológico.",
            '',
            'Reglas:',
            '- Público: visitantes del zoológico, niños y adultos. Español neutro y sencillo.',
            '- Usa solo datos verificables sobre la especie (hábitat, alimentación, comportamiento, características, conservación).',
            '- Pregunta solo datos generales del animal: no menciones el zoológico, sus visitantes ni lo que ocurre en él.',
            '- Da prioridad a los datos del zoológico incluidos abajo.',
            '- Cada pregunta tiene exactamente 3 opciones: 1 correcta y 2 incorrectas pero creíbles.',
            '- No uses "todas las anteriores" ni "ninguna de las anteriores".',
            '- Respuestas cortas y de longitud parecida.',
            '- No repitas preguntas.',
        ];

        $existing = [
            ...$card->quizQuestions()->latest('id')->limit(self::MAX_EXCLUDED_QUESTIONS)->pluck('question')->all(),
            ...$exclude,
        ];

        if ($existing) {
            $lines[] = '- No repitas ni reformules estas preguntas, que ya existen:';

            foreach ($existing as $question) {
                $lines[] = "  - {$question}";
            }
        }

        $facts = array_filter([
            'Descripción' => $species?->description,
            'Hábitat' => $species?->habitatLabel(),
            'Origen' => $species?->origin,
            'Alimentación' => $species?->diet?->label(),
            'Estado de conservación' => $species?->conservation_status?->label(),
        ], fn ($value) => filled($value));

        if ($facts) {
            $lines[] = '';
            $lines[] = 'Datos del zoológico sobre la especie:';

            foreach ($facts as $label => $value) {
                $lines[] = "- {$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Mismo formato que la importación por JSON (ImportQuizQuestionsRequest).
     *
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'correct_answer' => ['type' => 'string'],
                            'wrong_answers' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                                'minItems' => 2,
                                'maxItems' => 2,
                            ],
                        ],
                        'required' => ['question', 'correct_answer', 'wrong_answers'],
                    ],
                ],
            ],
            'required' => ['questions'],
        ];
    }

    /**
     * @param  array<mixed>|null  $json
     * @return list<array{question: string, correct_answer: string, wrong_answers: list<string>}>
     */
    private function parseQuestions(?array $json): array
    {
        $items = $json['questions'] ?? null;

        $questions = is_array($items)
            ? array_values(array_filter(array_map($this->normalizeItem(...), $items)))
            : [];

        if (! $questions) {
            throw QuizGenerationException::emptyResult();
        }

        return $questions;
    }

    /**
     * Descarta lo que no tenga la forma esperada. El resto de las reglas
     * (repetidas, longitudes) se aplican en la revisión y al guardar.
     *
     * @return array{question: string, correct_answer: string, wrong_answers: list<string>}|null
     */
    private function normalizeItem(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $question = $item['question'] ?? null;
        $correct = $item['correct_answer'] ?? null;
        $wrong = $item['wrong_answers'] ?? null;

        if (
            ! is_string($question) || trim($question) === ''
            || ! is_string($correct) || trim($correct) === ''
            || ! is_array($wrong) || count($wrong) !== 2
            || array_filter($wrong, fn ($answer) => ! is_string($answer) || trim($answer) === '')
        ) {
            return null;
        }

        return [
            'question' => trim($question),
            'correct_answer' => trim($correct),
            'wrong_answers' => array_map('trim', array_values($wrong)),
        ];
    }
}
