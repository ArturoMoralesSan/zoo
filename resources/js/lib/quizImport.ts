/*
 * Importación de preguntas en lote: prompt para un LLM, lectura del JSON
 * (pegado o subido) y validación de cada pregunta para la vista previa.
 *
 * Formato:
 *   { "questions": [ { "question": "…", "correct_answer": "…", "wrong_answers": ["…", "…"] } ] }
 *
 * La respuesta correcta va por nombre, no por posición: los duelos mezclan
 * las respuestas. El servidor vuelve a validar todo (ImportQuizQuestionsRequest).
 */

export interface QuizImportRow {
    question: string;
    correct_answer: string;
    wrong_answers: string[];
}

export type QuizRowStatus = 'ready' | 'error' | 'duplicate';

export interface QuizRowCheck {
    status: QuizRowStatus;
    messages: string[];
}

export interface QuizParseResult {
    rows: QuizImportRow[];
    // Error de lectura (JSON inválido o sin lista de preguntas).
    error: string | null;
}

export const MAX_QUESTION_LENGTH = 5000;
export const MAX_ANSWER_LENGTH = 1000;

export const EXAMPLE_IMPORT = {
    questions: [
        {
            question: '¿Cuál es el felino más grande de América?',
            correct_answer: 'El jaguar',
            wrong_answers: ['El puma', 'El ocelote'],
        },
        {
            question: '¿En qué momento del día suele cazar el jaguar?',
            correct_answer: 'Al atardecer y de noche',
            wrong_answers: ['Solo al mediodía', 'Solo en invierno'],
        },
    ],
};

/**
 * Texto comparable para detectar preguntas repetidas. Igual que
 * QuizQuestion::normalizeText() en PHP.
 */
export function normalizeQuestionText(text: string): string {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .replace(/¿/g, '?')
        .replace(/¡/g, '!')
        .replace(/\s+/g, ' ')
        .replace(/^[ ?!.]+|[ ?!.]+$/g, '');
}

/**
 * Prompt para generar las preguntas con un LLM (ChatGPT, Gemini…).
 */
export function buildQuizPrompt(
    card: {
        name: string;
        commonName?: string | null;
        scientificName?: string | null;
    },
    count: number,
    existing: string[],
): string {
    const species = [
        card.commonName,
        card.scientificName && `(${card.scientificName})`,
    ]
        .filter(Boolean)
        .join(' ');

    const lines = [
        `Genera ${count} preguntas de opción múltiple sobre ${species || card.name} para la tarjeta "${card.name}" de un juego educativo de un zoológico.`,
        '',
        'Reglas:',
        '- Público: visitantes del zoológico, niños y adultos. Español neutro y sencillo.',
        '- Usa solo datos verificables sobre la especie (hábitat, alimentación, comportamiento, características, conservación).',
        '- Cada pregunta tiene exactamente 3 opciones: 1 correcta y 2 incorrectas pero creíbles.',
        '- No uses "todas las anteriores" ni "ninguna de las anteriores".',
        '- Respuestas cortas y de longitud parecida.',
        '- No repitas preguntas.',
    ];

    if (existing.length) {
        lines.push(
            '- No repitas ni reformules estas preguntas, que ya existen:',
            ...existing.map((question) => `  - ${question}`),
        );
    }

    /*
     * Entrega: primero un archivo descargable; si el asistente no puede
     * crear archivos, JSON en texto plano de una sola línea (sin bloque de
     * código ni Markdown), para copiarlo tal cual.
     */
    lines.push(
        '',
        'Formato de entrega (muy importante):',
        '1. Si puedes crear archivos, entrega el resultado como un archivo descargable llamado "preguntas.json" con el JSON y nada más.',
        '2. Si no puedes crear archivos, responde únicamente con el JSON en texto plano y sin formato:',
        '   - En una sola línea, sin saltos de línea ni sangría.',
        '   - Sin bloque de código, sin ```, sin Markdown y sin negritas.',
        '   - Sin ningún texto antes ni después (ni saludos, ni explicaciones, ni notas).',
        '   - Usa comillas rectas (") en el JSON, no comillas tipográficas (“ ”).',
        '',
        'Estructura exacta del JSON (este es un ejemplo con una pregunta):',
        JSON.stringify({
            questions: [
                {
                    question: 'Texto de la pregunta',
                    correct_answer: 'Respuesta correcta',
                    wrong_answers: [
                        'Respuesta incorrecta 1',
                        'Respuesta incorrecta 2',
                    ],
                },
            ],
        }),
    );

    return lines.join('\n');
}

/*
 * Nombres de campo aceptados: los LLM a veces los traducen.
 */
const QUESTION_KEYS = ['question', 'pregunta'];
const CORRECT_KEYS = ['correct_answer', 'respuesta_correcta', 'correct'];
const WRONG_KEYS = [
    'wrong_answers',
    'respuestas_incorrectas',
    'incorrect_answers',
];

const pick = (item: Record<string, unknown>, keys: string[]): unknown =>
    keys.map((key) => item[key]).find((value) => value !== undefined);

const asText = (value: unknown): string =>
    typeof value === 'string' || typeof value === 'number'
        ? String(value).trim()
        : '';

/*
 * Quita el texto que los LLM ponen alrededor del JSON (```json … ```,
 * "Aquí tienes…").
 */
function extractJson(text: string): string {
    const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/i);

    if (fenced) {
        return fenced[1].trim();
    }

    const start = text.search(/[[{]/);

    if (start === -1) {
        return text.trim();
    }

    const closing = text[start] === '[' ? ']' : '}';
    const end = text.lastIndexOf(closing);

    return end > start ? text.slice(start, end + 1) : text.slice(start);
}

/*
 * "línea 4, columna 12" a partir del error de JSON.parse, si se puede.
 */
function errorLocation(json: string, error: unknown): string {
    const message = error instanceof Error ? error.message : '';

    const lineColumn = message.match(/line (\d+) column (\d+)/i);

    if (lineColumn) {
        return ` (línea ${lineColumn[1]}, columna ${lineColumn[2]})`;
    }

    const position = message.match(/position (\d+)/i);

    if (position) {
        const before = json.slice(0, Number(position[1])).split('\n');

        return ` (línea ${before.length}, columna ${before[before.length - 1].length + 1})`;
    }

    return '';
}

export function parseQuizImport(text: string): QuizParseResult {
    if (!text.trim()) {
        return { rows: [], error: null };
    }

    const json = extractJson(text);
    let data: unknown;

    try {
        data = JSON.parse(json);
    } catch (error) {
        return {
            rows: [],
            error: `El texto no es un JSON válido${errorLocation(json, error)}. ¿Copiaste toda la respuesta?`,
        };
    }

    const items = Array.isArray(data)
        ? data
        : data && typeof data === 'object'
          ? (pick(data as Record<string, unknown>, [
                'questions',
                'preguntas',
            ]) as unknown)
          : null;

    if (!Array.isArray(items)) {
        return {
            rows: [],
            error: 'No se encontró la lista "questions" en el JSON.',
        };
    }

    return {
        rows: items.map((item) => {
            const record =
                item && typeof item === 'object'
                    ? (item as Record<string, unknown>)
                    : {};
            const wrong = pick(record, WRONG_KEYS);

            return {
                question: asText(pick(record, QUESTION_KEYS)),
                correct_answer: asText(pick(record, CORRECT_KEYS)),
                wrong_answers: Array.isArray(wrong) ? wrong.map(asText) : [],
            };
        }),
        error: null,
    };
}

/**
 * Estado de una pregunta en la vista previa. `seen` acumula las preguntas
 * válidas anteriores del lote para detectar repetidas.
 */
export function checkQuizRow(
    row: QuizImportRow,
    existing: Set<string>,
    seen: Set<string>,
): QuizRowCheck {
    const messages: string[] = [];

    if (!row.question) {
        messages.push('Falta el texto de la pregunta.');
    } else if (row.question.length > MAX_QUESTION_LENGTH) {
        messages.push(`La pregunta supera ${MAX_QUESTION_LENGTH} caracteres.`);
    }

    if (!row.correct_answer) {
        messages.push('Falta la respuesta correcta.');
    }

    if (row.wrong_answers.length !== 2) {
        messages.push(
            `Debe haber exactamente 2 respuestas incorrectas (hay ${row.wrong_answers.length}).`,
        );
    } else if (row.wrong_answers.some((answer) => !answer)) {
        messages.push('Una respuesta incorrecta está vacía.');
    }

    const answers = [row.correct_answer, ...row.wrong_answers];

    if (answers.some((answer) => answer.length > MAX_ANSWER_LENGTH)) {
        messages.push(`Una respuesta supera ${MAX_ANSWER_LENGTH} caracteres.`);
    }

    const filled = answers.filter(Boolean).map(normalizeQuestionText);

    if (new Set(filled).size < filled.length) {
        messages.push('Las respuestas se repiten.');
    }

    if (messages.length) {
        return { status: 'error', messages };
    }

    const key = normalizeQuestionText(row.question);

    if (existing.has(key)) {
        return {
            status: 'duplicate',
            messages: ['Ya existe en esta tarjeta.'],
        };
    }

    if (seen.has(key)) {
        return { status: 'duplicate', messages: ['Repetida en este lote.'] };
    }

    seen.add(key);

    return { status: 'ready', messages: [] };
}
