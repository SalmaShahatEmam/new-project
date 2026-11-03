<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Enum\SiteProblemPriorityEnum;
use App\Enum\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSiteProblemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'suggested_solutions',
            'drop_suggested_solutions',
            'remove_suggested_solutions',
            'add_suggested_solutions',
            'drop_attachments',
            'remove_attachments',
            'delete_attachments',
        ] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(['new', 'in_progress', 'solved'])],
            'priority' => ['sometimes', 'required', Rule::enum(SiteProblemPriorityEnum::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'founded_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'explanation' => ['sometimes', 'required', 'string'],
            'causes' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],

            'suggested_solutions' => ['sometimes', 'nullable', 'array'],
            'suggested_solutions.*' => ['required', 'string'],
            'drop_suggested_solutions' => ['sometimes', 'array'],
            'drop_suggested_solutions.*' => ['required'],
            'remove_suggested_solutions' => ['sometimes', 'array'],
            'remove_suggested_solutions.*' => ['required'],
            'add_suggested_solutions' => ['sometimes', 'array'],
            'add_suggested_solutions.*' => ['required', 'string'],

            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif,pdf,doc,docx,xls,xlsx',
                'max:10240',
            ],
            'drop_attachments' => ['sometimes', 'array'],
            'drop_attachments.*' => ['required', 'string'],
            'remove_attachments' => ['sometimes', 'array'],
            'remove_attachments.*' => ['required', 'string'],
            'delete_attachments' => ['sometimes', 'array'],
            'delete_attachments.*' => ['required', 'string'],
            'drop_all_attachments' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $input = $this->all();
            $allowedFields = [
                'status',
                'assigned_to',
                'priority',
                'explanation',
                'causes',
                'notes',
                'founded_by',
                'suggested_solutions',
                'drop_suggested_solutions',
                'remove_suggested_solutions',
                'add_suggested_solutions',
                'attachments',
                'drop_attachments',
                'remove_attachments',
                'delete_attachments',
                'drop_all_attachments',
            ];

            $hasAnyField = false;
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $input)) {
                    $hasAnyField = true;
                    break;
                }
            }

            if (! $hasAnyField && ! $this->hasFile('attachments')) {
                $validator->errors()->add('status', __('validation.site_problem_update_required_fields'));
            }
        });
    }
}
