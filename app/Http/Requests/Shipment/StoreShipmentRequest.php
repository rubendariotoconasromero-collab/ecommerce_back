<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-pedidos') ?? false;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'courier_name'    => ['nullable', 'string', 'max:100'],
            'handler_id'      => ['nullable', 'uuid', 'exists:users,id'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tracking_number.max' => 'El número de seguimiento no puede superar 100 caracteres.',
            'courier_name.max'    => 'El nombre del courier no puede superar 100 caracteres.',
            'handler_id.uuid'     => 'El ID del gestor debe ser un UUID válido.',
            'handler_id.exists'   => 'El gestor especificado no existe.',
        ];
    }
}
