<?php

namespace App\Http\Requests\Return;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-pedidos') ?? false;
    }

    public function rules(): array
    {
        return [
            'notes'         => ['nullable', 'string', 'max:500'],
            'refund_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'refund_amount.numeric' => 'El monto de reembolso debe ser un número.',
            'refund_amount.min'     => 'El monto de reembolso debe ser mayor a cero.',
        ];
    }
}
