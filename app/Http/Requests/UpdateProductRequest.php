<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'category_id'               => 'required|exists:categories,id',
            'sku'                       => ['required', 'string', 'max:50', Rule::unique('products')->ignore($productId)],
            'name'                      => 'required|string|max:255',
            'description'               => 'nullable|string',
            'base_price'                => 'required|numeric|min:0',
            'cost_price'                => 'required|numeric|min:0',
            'sale_price'                => 'required|numeric|min:0',
            'production_lead_time_days' => 'required|integer|min:0',
            'attributes'                => 'nullable|array',
            'is_active'                 => 'boolean',
            'is_featured'               => 'boolean'
        ];
    }
}
