<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Enum\SiteProblemPriorityEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteProblemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user('api')?->id,
            'founded_by' => $this->input('founded_by', $this->user('api')?->id),
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'founded_by' => ['nullable', 'integer', 'exists:users,id'],
            'explanation' => ['required', 'string'],
            'causes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['new', 'in_progress', 'solved'])],
            'priority' => ['sometimes', 'required', Rule::enum(SiteProblemPriorityEnum::class)],
            'suggested_solutions' => ['nullable', 'array'],
            'suggested_solutions.*' => ['required', 'string'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
        ];
    }
}
