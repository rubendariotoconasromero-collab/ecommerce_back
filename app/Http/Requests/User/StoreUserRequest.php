<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id'       => 'required|exists:roles,id',
            'name'          => 'required|string|max:150',
            'email'         => 'required|email|max:150|unique:users,email',
            'password'      => 'required|string|min:8',
            'phone'         => 'nullable|string|max:30',
            'is_active'     => 'nullable|boolean',
            'customer_type' => 'nullable|in:individual,business',
            'business_name' => 'nullable|string|max:150|required_if:customer_type,business',
            'tax_id'        => 'nullable|string|max:50',
        ];
    }
}
