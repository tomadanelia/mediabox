<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterPayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return

            $this->input('USERNAME') === config('interpay.username') &&
            $this->input('PASSWORD') === config('interpay.password');
    }

    public function rules(): array
    {
        $rules = [
          'OP' => 'required|in:debt,paysuccess',
            'USERNAME' => 'required|string',
            'PASSWORD' => 'required|string',
            'PROVIDER' => 'required|string',
            'TERMINAL' => 'required|string',
            'SERVICE_ID' => 'required|string',
            'CUSTOMER_ID' => 'required|string',
        ];
        if ($this->input('OP') === 'paysuccess') {
        $rules['PAY_SRC'] = 'required|in:web';
        $rules['PAY_AMOUNT'] = 'required|integer|min:1';
        $rules['PAYMENT_ID'] = 'required|string';
    }
    
    return $rules;
    }

    protected function failedAuthorization()
    {
        abort(401, 'Authentication failed for InterPay request.');
    }
}
