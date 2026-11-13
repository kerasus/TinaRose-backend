<?php

namespace App\Http\Requests\Correspondence;

use Illuminate\Foundation\Http\FormRequest;

class CreateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
