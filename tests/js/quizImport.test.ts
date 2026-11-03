import { describe, expect, it } from 'vitest';

import {
    EXAMPLE_IMPORT,
    buildQuizPrompt,
    checkQuizRow,
    normalizeQuestionText,
    parseQuizImport,
} from '@/lib/quizImport';
import type { QuizImportRow } from '@/lib/quizImport';

const row = (overrides: Partial<QuizImportRow> = {}): QuizImportRow => ({
    question: '¿Cuál es el felino más grande de América?',
    correct_answer: 'El jaguar',
    wrong_answers: ['El puma', 'El ocelote'],
    ...overrides,
});

describe('parseQuizImport', () => {
    it('lee el objeto con "questions"', () => {
        const result = parseQuizImport(JSON.stringify(EXAMPLE_IMPORT));

        expect(result.error).toBeNull();
        expect(result.rows).toHaveLength(2);
        expect(result.rows[0]).toEqual(EXAMPLE_IMPORT.questions[0]);
    });

    it('acepta un arreglo suelto', () => {
        const result = parseQuizImport(
            JSON.stringify(EXAMPLE_IMPORT.questions),
        );

        expect(result.rows).toHaveLength(2);
    });

    it('quita los bloques ```json y el texto alrededor', () => {
        const text = `¡Claro! Aquí tienes las preguntas:\n\n\`\`\`json\n${JSON.stringify(EXAMPLE_IMPORT, null, 2)}\n\`\`\`\n\nEspero que te sirvan.`;

        expect(parseQuizImport(text).rows).toHaveLength(2);
    });

    it('encuentra el JSON aunque no tenga bloque de código', () => {
        const text = `Aquí están: ${JSON.stringify(EXAMPLE_IMPORT)} ¡Suerte!`;

        expect(parseQuizImport(text).rows).toHaveLength(2);
    });

    it('acepta nombres de campo traducidos', () => {
        const text = JSON.stringify({
            preguntas: [
                {
                    pregunta: '¿Qué come el jaguar?',
                    respuesta_correcta: 'Carne',
                    respuestas_incorrectas: ['Hierba', 'Frutas'],
                },
            ],
        });

        expect(parseQuizImport(text).rows[0]).toEqual({
            question: '¿Qué come el jaguar?',
            correct_answer: 'Carne',
            wrong_answers: ['Hierba', 'Frutas'],
        });
    });

    it('indica dónde está el error de JSON', () => {
        const result = parseQuizImport(
            '{\n  "questions": [\n    { "question": "Hola", }\n  ]\n}',
        );

        expect(result.rows).toEqual([]);
        expect(result.error).toMatch(
            /no es un JSON válido \(línea 3, columna \d+\)/,
        );
    });

    it('avisa si falta la lista de preguntas', () => {
        expect(parseQuizImport('{"items": []}').error).toContain('"questions"');
    });

    it('un texto vacío no es un error', () => {
        expect(parseQuizImport('   ')).toEqual({ rows: [], error: null });
    });
});

describe('checkQuizRow', () => {
    it('marca una pregunta válida como lista', () => {
        expect(checkQuizRow(row(), new Set(), new Set())).toEqual({
            status: 'ready',
            messages: [],
        });
    });

    it('detecta campos faltantes y número de respuestas', () => {
        const check = checkQuizRow(
            row({ question: '', correct_answer: '', wrong_answers: ['Uno'] }),
            new Set(),
            new Set(),
        );

        expect(check.status).toBe('error');
        expect(check.messages).toEqual([
            'Falta el texto de la pregunta.',
            'Falta la respuesta correcta.',
            'Debe haber exactamente 2 respuestas incorrectas (hay 1).',
        ]);
    });

    it('detecta respuestas repetidas sin importar acentos ni mayúsculas', () => {
        const check = checkQuizRow(
            row({ wrong_answers: ['el JAGUAR', 'El ocelote'] }),
            new Set(),
            new Set(),
        );

        expect(check.messages).toContain('Las respuestas se repiten.');
    });

    it('detecta preguntas que ya existen o se repiten en el lote', () => {
        const existing = new Set([
            normalizeQuestionText('Cual es el FELINO mas grande de america'),
        ]);

        expect(checkQuizRow(row(), existing, new Set()).status).toBe(
            'duplicate',
        );

        const seen = new Set<string>();
        expect(checkQuizRow(row(), new Set(), seen).status).toBe('ready');
        expect(checkQuizRow(row(), new Set(), seen).messages).toEqual([
            'Repetida en este lote.',
        ]);
    });
});

describe('normalizeQuestionText', () => {
    it('igual que QuizQuestion::normalizeText()', () => {
        expect(
            normalizeQuestionText('  ¿Cuál es   el FELINO más grande?  '),
        ).toBe('cual es el felino mas grande');
        expect(normalizeQuestionText('¡Niño!')).toBe('nino');
        expect(normalizeQuestionText('Qué come el ñandú.')).toBe(
            'que come el nandu',
        );
    });
});

describe('buildQuizPrompt', () => {
    it('incluye la tarjeta, la especie, la cantidad y las preguntas existentes', () => {
        const prompt = buildQuizPrompt(
            {
                name: 'Jaguar del Norte',
                commonName: 'Jaguar',
                scientificName: 'Panthera onca',
            },
            15,
            ['¿Qué come el jaguar?'],
        );

        expect(prompt).toContain('Genera 15 preguntas');
        expect(prompt).toContain('Jaguar (Panthera onca)');
        expect(prompt).toContain('"Jaguar del Norte"');
        expect(prompt).toContain('  - ¿Qué come el jaguar?');
        expect(prompt).toContain('"correct_answer"');
        // Pide un archivo descargable o JSON plano en una línea, sin bloque de código.
        expect(prompt).toContain('"preguntas.json"');
        expect(prompt).toContain('Sin bloque de código');
        expect(prompt).toContain(
            '{"questions":[{"question":"Texto de la pregunta","correct_answer":"Respuesta correcta","wrong_answers":["Respuesta incorrecta 1","Respuesta incorrecta 2"]}]}',
        );
    });
});
