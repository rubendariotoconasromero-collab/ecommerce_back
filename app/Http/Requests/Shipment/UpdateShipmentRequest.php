<?php

namespace App\Http\Requests\Shipment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'courier_name'    => ['nullable', 'string', 'max:100'],
            'handler_id'      => ['nullable', 'uuid', 'exists:users,id'],
            'failure_reason'  => ['nullable', 'string', 'max:1000'],
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
            'failure_reason.max'  => 'El motivo de fallo no puede superar 1000 caracteres.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Cuando se llama desde el endpoint /fail, failure_reason es obligatorio
            $routeAction = $this->route()?->getActionMethod();
            if ($routeAction === 'fail' && empty($this->input('failure_reason'))) {
                $v->errors()->add('failure_reason', 'El motivo de fallo es obligatorio al marcar un envío como fallido.');
            }
        });
    }
}
