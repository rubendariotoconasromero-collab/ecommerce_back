<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-pedidos') ?? false;
    }

    public function rules(): array
    {
        return [
            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'internal_notes.max' => 'Las notas internas no pueden superar 1000 caracteres.',
        ];
    }
}
