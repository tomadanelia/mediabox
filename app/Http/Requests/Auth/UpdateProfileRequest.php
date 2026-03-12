<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:50'],
            'username'  => [
                'nullable', 
                'string', 
                'max:50', 
                Rule::unique('users')->ignore($this->user()->id)
            ],
            'avatar_url' => ['nullable', 'url'],
        ];
    }
}