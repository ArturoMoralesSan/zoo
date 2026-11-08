<?php

namespace App\Http\Requests\Admin\Card;

use App\Concerns\AssetCreditValidationRules;
use App\Enums\AssetSource;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para crear o editar un crédito de un recurso de la tarjeta.
 */
class StoreCardCreditRequest extends FormRequest
{
    use AssetCreditValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->creditRules(
            $this->route('card'),
            $this->route('credit')
        );
    }

    public function after(): array
    {
        return [
            $this->creditChecks($this->route('card'), $this->route('credit')),
        ];
    }

    public function messages(): array
    {
        return $this->creditMessages();
    }

    public function attributes(): array
    {
        return $this->creditAttributes();
    }

    /**
     * Atributos del crédito a partir de los datos validados.
     *
     * @return array<string, mixed>
     */
    public function creditData(): array
    {
        $validated = $this->validated();
        $isAi = $validated['source_name'] === AssetSource::Ai->value;
        $current = $this->route('credit');

        return [
            'asset' => $validated['asset'],
            'role' => $validated['role'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'license_id' => $validated['license_id'],
            'source_name' => $validated['source_name'],
            'source_url' => $validated['source_url'] ?? null,
            'title' => $validated['title'] ?? null,
            'author_name' => $validated['author_name'] ?? null,
            'author_url' => $validated['author_url'] ?? null,
            'acquired_at' => $validated['acquired_at'] ?? null,
            'modified' => $this->boolean('modified'),
            'modification_note' => $validated['modification_note'] ?? null,
            'attribution_override' => $validated['attribution_override'] ?? null,
            'based_on_asset' => $validated['based_on_asset'] ?? null,
            'notes' => $validated['notes'] ?? null,

            /*
             * La revisión de la IA se fecha la primera vez; si se edita,
             * se conserva la fecha original.
             */
            'ai_prompt' => $isAi ? ($validated['ai_prompt'] ?? null) : null,
            'ai_checked_at' => $isAi ? ($current?->ai_checked_at ?? now()) : null,
        ];
    }
}
