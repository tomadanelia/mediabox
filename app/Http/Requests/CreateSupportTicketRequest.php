<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:bug_report,channel_issue,billing,feedback',
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:5000',
            'device_info' => 'nullable|array', 
        ];
    }
}