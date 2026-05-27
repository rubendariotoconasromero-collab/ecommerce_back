<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('modulo-usuarios') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'role_id'       => 'nullable|exists:roles,id',
            'name'          => 'required|string|max:150',
            'email'         => ['required', 'email', 'max:150', Rule::unique('users')->ignore($userId)],
            'password'      => 'nullable|string|min:8',
            'phone'         => 'nullable|string|max:30',
            'is_active'     => 'nullable|boolean',
            'customer_type' => 'nullable|in:individual,business',
            'business_name' => 'nullable|string|max:150|required_if:customer_type,business',
            'tax_id'        => 'nullable|string|max:50',
        ];
    }
}
