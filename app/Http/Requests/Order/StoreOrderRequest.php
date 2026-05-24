<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'      => 'required|uuid|exists:customers,id',
            'shipping_address' => 'required|string|max:1000',
            'notes'            => 'nullable|string|max:2000',
            'expected_delivery_date' => 'nullable|date|after_or_equal:today',

            'items'                          => 'required|array|min:1',
            'items.*.product_id'             => 'required|uuid|exists:products,id',
            'items.*.quantity'               => 'required|integer|min:1|max:99999',
            'items.*.customization_notes'    => 'nullable|string|max:1000',
            'items.*.reference_image'        => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists'           => 'El cliente no existe en el sistema.',
            'items.required'               => 'La orden debe contener al menos un producto.',
            'items.min'                    => 'La orden debe contener al menos un producto.',
            'items.*.product_id.exists'    => 'Uno de los productos seleccionados no existe.',
            'items.*.quantity.min'         => 'La cantidad mínima por producto es 1.',
            'expected_delivery_date.after_or_equal' => 'La fecha de entrega no puede ser anterior a hoy.',
        ];
    }

    /**
     * Validación adicional tras las reglas básicas:
     * - El cliente debe estar activo.
     * - No puede haber productos duplicados en el mismo pedido.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Validar cliente activo
            if ($this->filled('customer_id')) {
                $customer = \App\Models\Customer::find($this->customer_id);
                if ($customer && !$customer->is_active) {
                    $v->errors()->add('customer_id', 'El cliente seleccionado está inactivo.');
                }
            }

            // Validar que no haya productos duplicados
            if ($this->has('items')) {
                $productIds = array_column($this->items ?? [], 'product_id');
                if (count($productIds) !== count(array_unique($productIds))) {
                    $v->errors()->add('items', 'No se pueden agregar dos líneas del mismo producto. Incremente la cantidad en su lugar.');
                }
            }
        });
    }
}
