<?php

namespace App\Http\Requests\Admin\CardTransfer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'include_qr_tokens' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'include_qr_tokens' => 'incluir tokens de QR',
        ];
    }
}
