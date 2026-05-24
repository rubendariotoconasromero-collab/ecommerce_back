<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'featured' => 'nullable|string|in:true,false',
            'active' => 'nullable|string|in:true,false',
            'nopaginate' => 'nullable|string|in:true,false',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'search' => 'nullable|string|max:100',
        ];
    }
}
