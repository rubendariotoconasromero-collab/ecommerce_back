<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-clientes') ?? false;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')->id;

        return [
            'customer_type' => 'sometimes|required|in:individual,business',
            'name'          => 'sometimes|required|string|max:150',
            'email'         => ['sometimes', 'required', 'email', 'max:150',
                                Rule::unique('customers')->ignore($customerId)],
            'business_name' => 'nullable|string|max:150|required_if:customer_type,business',
            'tax_id'        => 'nullable|string|max:50',
            'phone'         => 'nullable|string|max:30',
            'user_id'       => 'nullable|uuid|exists:users,id',
            'is_active'     => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required_if' => 'La razón social es obligatoria para clientes tipo empresa.',
        ];
    }
}
