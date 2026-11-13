<?php

namespace App\Http\Requests\Correspondence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'recipients.*.type' => ['nullable', Rule::in(['to', 'cc'])],
            'recipients.*.due_at' => ['nullable', 'date'],
        ];
    }
}