<?php

namespace App\Http\Requests\Correspondence;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_note' => ['nullable', 'string'],
        ];
    }
}
