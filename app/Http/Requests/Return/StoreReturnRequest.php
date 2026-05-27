<?php

namespace App\Http\Requests\Return;

use App\Models\OrderReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-pedidos') ?? false;
    }

    public function rules(): array
    {
        return [
            'return_type'   => ['required', 'string', Rule::in(OrderReturn::TYPES)],
            'order_item_id' => ['nullable', 'uuid', 'exists:order_items,id'],
            'reason'        => ['nullable', 'string', 'max:1000'],
            'refund_amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'return_type.required' => 'El tipo de devolución es obligatorio.',
            'return_type.in'       => 'El tipo de devolución debe ser: ' . implode(', ', OrderReturn::TYPES) . '.',
            'order_item_id.uuid'   => 'El ID de ítem debe ser un UUID válido.',
            'order_item_id.exists' => 'El ítem especificado no existe.',
            'refund_amount.numeric'=> 'El monto de reembolso debe ser un número.',
            'refund_amount.min'    => 'El monto de reembolso debe ser mayor a cero.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $type   = $this->input('return_type');
            $itemId = $this->input('order_item_id');

            if ($type === 'partial' && empty($itemId)) {
                $v->errors()->add('order_item_id', 'Las devoluciones parciales requieren especificar el ítem afectado.');
            }

            if ($type === 'full' && !empty($itemId)) {
                $v->errors()->add('order_item_id', 'Las devoluciones completas no deben especificar un ítem particular.');
            }
        });
    }
}
