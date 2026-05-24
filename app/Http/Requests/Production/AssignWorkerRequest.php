<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_worker_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_worker_id.uuid'  => 'El ID del trabajador debe ser un UUID válido.',
            'assigned_worker_id.exists'=> 'El trabajador especificado no existe.',
        ];
    }
}
