<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint público sin autenticación
    }

    public function rules(): array
    {
        return [
            'name'               => 'required|string|max:255',
            'tax_id'             => 'required|string|max:50',
            'phone'              => 'required|string|max:30',
            'email'              => 'nullable|email|max:255',
            'address'            => 'required|string|max:500',
            'city'               => 'required|string|max:100',
            'reference'          => 'nullable|string|max:255',
            'notes'              => 'nullable|string|max:2000',
            'items'              => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1|max:9999',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $ids = array_column($this->input('items', []), 'product_id');
            if (count($ids) !== count(array_unique($ids))) {
                $v->errors()->add('items', 'No puedes incluir el mismo producto dos veces en el pedido.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'El nombre o razón social es requerido.',
            'tax_id.required'           => 'El NIT/CI es requerido.',
            'phone.required'            => 'El teléfono de contacto es requerido.',
            'address.required'          => 'La dirección de entrega es requerida.',
            'city.required'             => 'La ciudad es requerida.',
            'items.required'            => 'Debes agregar al menos un producto al pedido.',
            'items.min'                 => 'Debes agregar al menos un producto al pedido.',
            'items.*.product_id.exists' => 'Uno de los productos seleccionados no existe o fue dado de baja.',
            'items.*.quantity.min'      => 'La cantidad mínima por producto es 1.',
            'items.*.quantity.max'      => 'La cantidad máxima por producto es 9,999 unidades.',
        ];
    }
}
