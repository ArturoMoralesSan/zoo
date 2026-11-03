<?php

namespace App\Concerns;

use App\Models\Card;
use Illuminate\Validation\Rule;

trait CardMessagesValidationRules
{
    /**
     * Reglas de los mensajes del resultado del duelo de la tarjeta.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function cardMessagesRules(): array
    {
        $message = [
            'nullable',
            'string',
            'max:255',
        ];

        return [
            'messages_source' => [
                'required',
                Rule::in([
                    Card::MESSAGES_DEFAULT,
                    Card::MESSAGES_CARD,
                ]),
            ],

            'win_message' => $message,

            'retry_message' => $message,

            'locked_message' => $message,
        ];
    }
}
