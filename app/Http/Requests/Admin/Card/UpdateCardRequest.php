<?php

namespace App\Http\Requests\Admin\Card;

use App\Enums\CardRarity;
use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'species_id' => [
                'required',
                'integer',
                'exists:species,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'rarity' => [
                'required',
                'string',
                Rule::enum(CardRarity::class),
            ],

            'edition' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'card_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'model_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'model_file' => [
                'nullable',
                'file',
                'mimes:glb,gltf',
                'max:51200',
            ],

            'model_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'model_format' => [
                'nullable',
                'string',
                'max:50',
            ],

            'model_description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'astucia' => [
                'required',
                'integer',
                'between:1,100',
            ],

            'rapidez' => [
                'required',
                'integer',
                'between:1,100',
            ],

            'fuerza' => [
                'required',
                'integer',
                'between:1,100',
            ],

            'duel_settings_source' => [
                'required',
                'string',
                Rule::in([
                    Card::DUEL_SETTINGS_RARITY,
                    Card::DUEL_SETTINGS_CARD,
                ]),
            ],

            'questions_per_duel' => [
                'nullable',
                'required_if:duel_settings_source,'.Card::DUEL_SETTINGS_CARD,
                'integer',
                'min:1',
                'max:50',
            ],

            'retries' => [
                'nullable',
                'required_if:duel_settings_source,'.Card::DUEL_SETTINGS_CARD,
                'integer',
                'min:1',
                'max:100',
            ],

            'cooldown_minutes' => [
                'nullable',
                'required_if:duel_settings_source,'.Card::DUEL_SETTINGS_CARD,
                'integer',
                'min:1',
                'max:43200',
            ],

            'questions' => [
                'nullable',
                'array',
            ],

            'questions.*.id' => [
                'nullable',
                'integer',
            ],

            'questions.*.question' => [
                'required',
                'string',
                'max:5000',
            ],

            'questions.*.is_active' => [
                'nullable',
                'boolean',
            ],

            'questions.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'questions.*.answers' => [
                'required',
                'array',
                'min:2',
            ],

            'questions.*.answers.*.id' => [
                'nullable',
                'integer',
            ],

            'questions.*.answers.*.answer' => [
                'required',
                'string',
                'max:1000',
            ],

            'questions.*.answers.*.is_correct' => [
                'required',
                'boolean',
            ],

            'questions.*.answers.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $questions = $this->input('questions');
            if (is_array($questions)) {
                foreach ($questions as $qIndex => $q) {
                    if (isset($q['answers']) && is_array($q['answers'])) {
                        $correctCount = collect($q['answers'])
                            ->filter(fn ($ans) => filter_var($ans['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
                            ->count();

                        if ($correctCount !== 1) {
                            $validator->errors()->add(
                                "questions.{$qIndex}.answers",
                                'Cada pregunta debe tener exactamente una opción marcada como correcta.'
                            );
                        }
                    }
                }
            }
        });
    }
}
