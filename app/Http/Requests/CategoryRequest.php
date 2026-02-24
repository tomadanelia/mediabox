<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    
    public function rules(): array
    {
        $categoryId = $this->route('categoryId'); 

        return [
            'name_ka' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('channel_categories', 'name_ka')->ignore($categoryId) 
            ],
            'name_en' => [
                'required', 
                'string', 
                'max:255', 
                Rule::unique('channel_categories', 'name_en')->ignore($categoryId)
            ],
            'description_en' => ['nullable', 'string'],
            'description_ka' => ['nullable', 'string'],
            'icon_url' => ['nullable', 'string', 'max:255'],
        ];
    }
}