<?php

namespace App\Http\Requests\Correspondence;

use App\Enums\Correspondence\CorrespondenceConfidentiality;
use App\Enums\Correspondence\CorrespondencePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCorrespondenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::enum(CorrespondencePriority::class)],
            'confidentiality' => ['nullable', Rule::enum(CorrespondenceConfidentiality::class)],
            'due_at' => ['nullable', 'date'],
            'thread_root_id' => ['nullable', 'integer', 'exists:correspondences,id'],
            'reply_to_id' => ['nullable', 'integer', 'exists:correspondences,id'],

            // ساختار جدید: هر گیرنده با مهلت اختیاری مستقل
            'recipients' => ['nullable', 'array'],
            'recipients.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'recipients.*.type' => ['nullable', Rule::in(['to', 'cc'])],
            'recipients.*.due_at' => ['nullable', 'date'],

            // ساختار قدیمی (سازگاری رو به عقب)
            'to' => ['nullable', 'array'],
            'to.*' => ['integer', 'exists:users,id'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['integer', 'exists:users,id'],
        ];
    }
}