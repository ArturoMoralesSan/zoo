/**
 * Mensajes del resultado de un duelo (victoria, reintento, sin intentos).
 *
 * Se configuran por rareza y, opcionalmente, por tarjeta. El servidor
 * reemplaza los marcadores y los envía en el campo `message` al responder.
 */
export type DuelMessageField =
    'win_message' | 'retry_message' | 'locked_message';

export type DuelMessages = Record<DuelMessageField, string>;

export const DUEL_MESSAGE_FIELDS: {
    key: DuelMessageField;
    label: string;
    help: string;
    outcome: 'won' | 'retry' | 'locked';
}[] = [
    {
        key: 'win_message',
        label: 'Victoria',
        help: 'Respondió todo bien y obtuvo la tarjeta.',
        outcome: 'won',
    },
    {
        key: 'retry_message',
        label: 'Reintento',
        help: 'Falló, pero todavía le quedan intentos.',
        outcome: 'retry',
    },
    {
        key: 'locked_message',
        label: 'Sin intentos',
        help: 'Falló y ya no le quedan intentos: la tarjeta queda bloqueada.',
        outcome: 'locked',
    },
];

/**
 * Marcadores que el servidor reemplaza al enviar el mensaje.
 */
export const DUEL_MESSAGE_PLACEHOLDERS = [
    { token: '{tarjeta}', description: 'Nombre de la tarjeta' },
    { token: '{correctas}', description: 'Respuestas correctas' },
    { token: '{total}', description: 'Total de preguntas' },
    { token: '{intentos_restantes}', description: 'Intentos que quedan' },
    { token: '{intentos_maximos}', description: 'Intentos por ronda' },
    { token: '{espera}', description: 'Tiempo de bloqueo, p. ej. "1 día"' },
];

/**
 * Minutos como texto legible (igual que en el servidor), p. ej. "1 día".
 */
export const readableMinutes = (minutes: number): string => {
    const units: [number, string, string][] = [
        [1440, 'día', 'días'],
        [60, 'hora', 'horas'],
        [1, 'minuto', 'minutos'],
    ];

    const parts: string[] = [];
    let rest = minutes;

    for (const [size, singular, plural] of units) {
        const amount = Math.floor(rest / size);

        if (amount > 0 && parts.length < 2) {
            parts.push(`${amount} ${amount === 1 ? singular : plural}`);
            rest -= amount * size;
        }
    }

    return parts.join(' ') || '0 minutos';
};

/**
 * Datos de la tarjeta para la vista previa de los mensajes.
 */
export interface DuelMessageContext {
    cardName: string;
    retries: number;
    cooldownMinutes: number;
}

/**
 * Mensaje con los marcadores reemplazados por valores de ejemplo
 * (3 preguntas; el puntaje y los intentos según el resultado).
 */
export const renderDuelMessage = (
    field: DuelMessageField,
    template: string,
    context: DuelMessageContext,
): string => {
    const values: Record<string, string> = {
        '{tarjeta}': context.cardName,
        '{correctas}': field === 'win_message' ? '3' : '2',
        '{total}': '3',
        '{intentos_restantes}':
            field === 'locked_message'
                ? '0'
                : String(Math.max(0, context.retries - 1)),
        '{intentos_maximos}': String(context.retries),
        '{espera}': readableMinutes(context.cooldownMinutes),
    };

    return template.replace(/\{[a-z_]+\}/g, (token) => values[token] ?? token);
};
