<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:50'],
            'username'  => ['nullable', 'string', 'max:50', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'email' => ['required_without:phone', 'nullable', 'email', 'unique:users'],
            'phone' => ['required_without:email', 'nullable', 'string', 'unique:users'],
        ];
    }
}