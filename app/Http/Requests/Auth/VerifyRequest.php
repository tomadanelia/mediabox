<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return true;
    }

   
    public function rules(): array
    {

    return [
        'user_id' => ['required_without:login', 'uuid', 'exists:users,id'],
        'login'   => ['required_without:user_id', 'string'],
        'code'    => ['required', 'string', 'size:6'],
    ];

    }
}
