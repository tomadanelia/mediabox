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
            'user_id'=>['required','uuid'],
            "code"=>['required','string','size:6'],
            'client'  => ['nullable', 'string', 'in:mobile,web'],
        ];
    }
}
