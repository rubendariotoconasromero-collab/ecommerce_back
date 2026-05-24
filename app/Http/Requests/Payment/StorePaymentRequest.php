<?php

namespace App\Http\Requests\Payment;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method'  => ['required', 'string', 'in:' . implode(',', Payment::METHODS)],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'transaction_id'  => ['nullable', 'string', 'max:255'],
            'mark_completed'  => ['sometimes', 'boolean'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in'       => 'Método de pago no válido. Opciones: ' . implode(', ', Payment::METHODS) . '.',
            'amount.required'         => 'El monto del pago es obligatorio.',
            'amount.numeric'          => 'El monto debe ser un número.',
            'amount.min'              => 'El monto debe ser mayor a cero.',
            'transaction_id.max'      => 'El ID de transacción no puede superar 255 caracteres.',
        ];
    }
}
