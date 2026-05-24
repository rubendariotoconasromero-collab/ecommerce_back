<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty_available'  => 'required|integer|min:0',
            'reorder_point'  => 'nullable|integer|min:0',
            'reason'         => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'qty_available.required' => 'Debe indicar la cantidad disponible.',
            'qty_available.min'      => 'La cantidad disponible no puede ser negativa.',
            'reason.required'        => 'El motivo del ajuste es obligatorio.',
            'reason.min'             => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
