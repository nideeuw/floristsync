<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /** Largest accepted product photo, in pixels, per side. */
    public const IMAGE_MAX_SIDE = 4000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'product_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:0'],
            'weight_gram' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in([Product::STATUS_AVAILABLE, Product::STATUS_UNAVAILABLE])],
            'is_active' => ['nullable', 'boolean'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width='.self::IMAGE_MAX_SIDE.',max_height='.self::IMAGE_MAX_SIDE,
            ],
        ];
    }
}
