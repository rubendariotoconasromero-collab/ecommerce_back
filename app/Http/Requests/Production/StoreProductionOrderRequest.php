<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-pedidos') ?? false;
    }

    public function rules(): array
    {
        return [
            'order_item_id'      => ['nullable', 'uuid', 'exists:order_items,id'],
            'assigned_worker_id' => ['nullable', 'uuid', 'exists:users,id'],
            'internal_notes'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_item_id.uuid'       => 'El ID del ítem debe ser un UUID válido.',
            'order_item_id.exists'     => 'El ítem especificado no existe.',
            'assigned_worker_id.uuid'  => 'El ID del trabajador debe ser un UUID válido.',
            'assigned_worker_id.exists'=> 'El trabajador especificado no existe.',
            'internal_notes.max'       => 'Las notas internas no pueden superar 1000 caracteres.',
        ];
    }
}
