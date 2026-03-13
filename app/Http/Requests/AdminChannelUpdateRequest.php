<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminChannelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
    
            'name_ka'  => 'sometimes|required|string|max:255',
            'name_en'  => 'sometimes|required|string|max:255',
            'icon_url' => 'sometimes|required|string|max:500'
        ];
    }
}