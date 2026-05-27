<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryAdjustmentBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-inventario') ?? false;
    }

    public function rules(): array
    {
        return [
            'adjustment_type'          => ['required', Rule::in(InventoryAdjustmentBatch::TYPES)],
            'notes'                    => 'nullable|string|max:1000',
            'lines'                    => 'required|array|min:1',
            'lines.*.product_id'       => 'required|uuid|exists:products,id',
            'lines.*.quantity'         => 'required|integer|min:1',
            'lines.*.new_cost_price'   => 'nullable|numeric|min:0',
            'lines.*.new_sale_price'   => 'nullable|numeric|min:0',
            'lines.*.line_notes'       => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'adjustment_type.required' => 'El tipo de ajuste es obligatorio.',
            'adjustment_type.in'       => 'El tipo de ajuste no es válido.',
            'lines.required'           => 'Debe agregar al menos un producto al ajuste.',
            'lines.min'                => 'Debe agregar al menos un producto al ajuste.',
            'lines.*.product_id.required' => 'Cada línea debe tener un producto.',
            'lines.*.product_id.exists'   => 'Uno o más productos no existen.',
            'lines.*.quantity.required'   => 'La cantidad de cada línea es obligatoria.',
            'lines.*.quantity.min'        => 'La cantidad debe ser mayor a cero.',
        ];
    }
}
