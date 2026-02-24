<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'name_ka' => ['required', 'string', 'max:255', 'unique:channel_categories,name_ka'],
            'name_en' => ['required', 'string', 'max:255', 'unique:channel_categories,name_en'],
            'description_en' => ['nullable', 'string'],
            'description_ka' => ['nullable', 'string'],
            'icon_url' => ['nullable', 'string', 'max:255'],
        ];
    }
}